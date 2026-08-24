<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Closure;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesDatabaseWithMigrationOwner;
use Tests\TestCase;

/**
 * These tests exercise the live PostgreSQL constraints. Application-side
 * validation cannot substitute for these race-condition guards.
 */
final class PhaseOneDatabaseConstraintsTest extends TestCase
{
    use RefreshesDatabaseWithMigrationOwner;

    public function test_phase_one_tables_remain_present_with_phase_seven_user_roles(): void
    {
        $tables = collect(DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename"
        ))->pluck('tablename')->all();

        $this->assertContains('users', $tables);
        $this->assertContains('password_reset_tokens', $tables);
        $this->assertContains('geographic_areas', $tables);
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('user_roles'));
    }

    public function test_identity_key_is_a_generated_always_bigint(): void
    {
        $column = DB::selectOne(
            "SELECT data_type, is_nullable, is_identity, identity_generation
             FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = 'users' AND column_name = 'id'"
        );

        $this->assertSame('bigint', $column->data_type);
        $this->assertSame('NO', $column->is_nullable);
        $this->assertSame('YES', $column->is_identity);
        $this->assertSame('ALWAYS', $column->identity_generation);
    }

    public function test_postgresql_exposes_every_phase_one_constraint_with_its_frozen_name(): void
    {
        $constraints = collect(DB::select(
            "SELECT c.relname AS table_name, con.contype::text AS type, con.conname AS name
             FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema()
               AND c.relname IN (
                   'geographic_areas', 'organizational_units', 'industries', 'organization_types',
                   'skills', 'study_programs', 'roles', 'users', 'password_credentials',
                   'email_verification_tokens', 'password_reset_tokens'
               )
             ORDER BY c.relname, con.contype::text, con.conname"
        ))->map(fn (object $constraint): string => "{$constraint->table_name}:{$constraint->type}:{$constraint->name}")
            ->all();

        $this->assertSame([
            'email_verification_tokens:f:fk_email_verification_tokens_user_id',
            'email_verification_tokens:p:pk_email_verification_tokens',
            'email_verification_tokens:u:uq_email_verification_tokens_hash',
            'geographic_areas:c:chk_geographic_areas_area_type',
            'geographic_areas:f:fk_geographic_areas_parent_geographic_area_id',
            'geographic_areas:p:pk_geographic_areas',
            'industries:p:pk_industries',
            'industries:u:uq_industries_code',
            'organization_types:p:pk_organization_types',
            'organization_types:u:uq_organization_types_code',
            'organizational_units:f:fk_organizational_units_parent_unit_id',
            'organizational_units:p:pk_organizational_units',
            'organizational_units:u:uq_organizational_units_code',
            'password_credentials:f:fk_password_credentials_user_id',
            'password_credentials:p:pk_password_credentials',
            'password_credentials:u:uq_password_credentials_user',
            'password_reset_tokens:f:fk_password_reset_tokens_user_id',
            'password_reset_tokens:p:pk_password_reset_tokens',
            'password_reset_tokens:u:uq_password_reset_tokens_hash',
            'roles:p:pk_roles',
            'roles:u:uq_roles_code',
            'skills:p:pk_skills',
            'skills:u:uq_skills_normalized_name',
            'study_programs:f:fk_study_programs_organizational_unit_id',
            'study_programs:p:pk_study_programs',
            'study_programs:u:uq_study_programs_code',
            'users:c:chk_users_status',
            'users:p:pk_users',
            'users:u:uq_users_email_normalized',
        ], $constraints);

        $index = DB::selectOne(
            "SELECT indexdef FROM pg_indexes
             WHERE schemaname = current_schema()
               AND tablename = 'geographic_areas'
               AND indexname = 'uq_geographic_areas_code'"
        );
        $this->assertStringContainsString('WHERE (code IS NOT NULL)', $index->indexdef);
    }

    public function test_required_role_catalog_seeder_uses_only_the_eleven_stored_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame([
            'AUDITOR',
            'CANDIDATE_ALUMNI',
            'CANDIDATE_EXTERNAL',
            'CANDIDATE_STUDENT_FINAL_YEAR',
            'CAREER_CENTER_MANAGER',
            'CAREER_CENTER_STAFF',
            'COMPANY_ADMIN',
            'COMPANY_RECRUITER',
            'HR_ADMIN',
            'SELECTOR',
            'SUPER_ADMIN',
        ], DB::table('roles')->orderBy('code')->pluck('code')->all());
        $this->assertSame(0, DB::table('roles')->where('code', 'PUBLIC')->count());
    }

    public function test_normalized_email_unique_constraint_rejects_duplicates(): void
    {
        $this->insertUser('same@example.test');

        $this->assertConstraintViolation('23505', 'uq_users_email_normalized', fn () =>
            $this->insertUser('same@example.test')
        );
    }

    public function test_status_check_rejects_invalid_values(): void
    {
        $this->assertConstraintViolation('23514', 'chk_users_status', fn () =>
            DB::table('users')->insert([
                'name' => 'Invalid Status',
                'email' => 'invalid-status@example.test',
                'email_normalized' => 'invalid-status@example.test',
                'status' => 'NOT_A_STATUS',
            ])
        );
    }

    public function test_foreign_key_rejects_a_nonexistent_user(): void
    {
        $this->assertConstraintViolation('23503', 'fk_password_reset_tokens_user_id', fn () =>
            DB::table('password_reset_tokens')->insert([
                'user_id' => 999999,
                'token_hash' => 'unreachable-user-token-hash',
                'expires_at' => now()->addHour(),
            ])
        );
    }

    public function test_phase_one_cascades_user_owned_credentials_and_tokens(): void
    {
        $userId = $this->insertUser('cascade@example.test');

        DB::table('password_credentials')->insert([
            'user_id' => $userId,
            'password_hash' => '$2y$04$fixture',
            'password_changed_at' => now(),
        ]);
        DB::table('email_verification_tokens')->insert([
            'user_id' => $userId,
            'token_hash' => 'email-token-hash',
            'expires_at' => now()->addHour(),
        ]);
        DB::table('password_reset_tokens')->insert([
            'user_id' => $userId,
            'token_hash' => 'reset-token-hash',
            'expires_at' => now()->addHour(),
        ]);

        DB::table('users')->where('id', $userId)->delete();

        $this->assertSame(0, DB::table('password_credentials')->count());
        $this->assertSame(0, DB::table('email_verification_tokens')->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_parent_organizational_unit_delete_is_restricted(): void
    {
        $parentId = DB::table('organizational_units')->insertGetId([
            'code' => 'PARENT',
            'name' => 'Parent Unit',
        ]);
        DB::table('organizational_units')->insert([
            'code' => 'CHILD',
            'name' => 'Child Unit',
            'parent_unit_id' => $parentId,
        ]);

        $this->assertConstraintViolation('23503', 'fk_organizational_units_parent_unit_id', fn () =>
            DB::table('organizational_units')->where('id', $parentId)->delete()
        );
    }

    public function test_partial_geographic_code_unique_index_rejects_duplicate_codes(): void
    {
        DB::table('geographic_areas')->insert([
            'code' => 'ID-JK',
            'name' => 'Jakarta',
            'area_type' => 'PROVINCE',
        ]);

        $this->assertConstraintViolation('23505', 'uq_geographic_areas_code', fn () =>
            DB::table('geographic_areas')->insert([
                'code' => 'ID-JK',
                'name' => 'Jakarta Duplicate',
                'area_type' => 'PROVINCE',
            ])
        );
    }

    private function insertUser(string $normalizedEmail): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => 'Constraint Test User',
            'email' => $normalizedEmail,
            'email_normalized' => $normalizedEmail,
            'status' => 'ACTIVE',
        ]);
    }

    private function assertConstraintViolation(string $sqlState, string $constraint, Closure $operation): void
    {
        try {
            $operation();
            $this->fail("Expected PostgreSQL to reject data via {$constraint}.");
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->getCode());
            $this->assertStringContainsString($constraint, $exception->getMessage());
        }
    }
}
