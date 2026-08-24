<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Domains\Identity\Support\EmailNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EmailNormalizationTest extends IdentityTestCase
{
    public static function caseVariants(): array
    {
        return [
            ['Budi.Santoso@Example.TEST'],
            ['BUDI.SANTOSO@EXAMPLE.TEST'],
            ['  budi.santoso@example.test  '],
            ['budi.santoso@Example.test'],
        ];
    }

    #[DataProvider('caseVariants')]
    public function test_case_and_whitespace_variants_normalize_to_one_identity(string $input): void
    {
        $this->assertSame('budi.santoso@example.test', EmailNormalizer::normalize($input));
    }

    public function test_normalization_is_conservative_and_does_not_merge_distinct_addresses(): void
    {
        // Dot-stripping and plus-addressing are provider conventions. Applying
        // them would silently merge addresses the business has not agreed are
        // the same person, so normalization deliberately leaves them alone.
        $this->assertNotSame(
            EmailNormalizer::normalize('a.b@example.test'),
            EmailNormalizer::normalize('ab@example.test'),
        );
        $this->assertNotSame(
            EmailNormalizer::normalize('a+tag@example.test'),
            EmailNormalizer::normalize('a@example.test'),
        );
    }

    public function test_duplicate_normalized_email_is_rejected_by_postgresql(): void
    {
        $this->makeUser('Budi@Example.test', password: null);

        try {
            $this->makeUser('BUDI@example.TEST', password: null);
            $this->fail('Expected uq_users_email_normalized to refuse the duplicate.');
        } catch (QueryException $e) {
            $this->assertSame('23505', $e->getCode());
            $this->assertStringContainsString('uq_users_email_normalized', $e->getMessage());
        }
    }

    public function test_raw_email_remains_available_for_display(): void
    {
        $user = $this->makeUser('Budi.Santoso@Example.TEST', password: null);

        $this->assertSame('Budi.Santoso@Example.TEST', $user->email);
        $this->assertSame('budi.santoso@example.test', $user->email_normalized);
    }

    public function test_users_email_carries_no_uniqueness_constraint(): void
    {
        // INV-001: email_normalized is the SOLE uniqueness rule. Two rows whose
        // display forms differ but normalize differently must both be storable.
        $this->makeUser('one@example.test', password: null);
        $this->makeUser('two@example.test', password: null);

        $indexes = DB::select("
            select indexdef from pg_indexes
            where schemaname = 'public' and tablename = 'users'
        ");

        $uniqueOnRawEmail = collect($indexes)->contains(function (object $i): bool {
            return str_contains($i->indexdef, 'UNIQUE')
                && preg_match('/\(email\)/', $i->indexdef) === 1;
        });

        $this->assertFalse($uniqueOnRawEmail, 'users.email must not carry a unique index.');
        $this->assertTrue(Schema::hasColumn('users', 'email_normalized'));
    }

    public function test_lookup_scope_uses_the_normalized_column(): void
    {
        $sql = User::query()->byEmail('Mixed@Example.test')->toSql();

        $this->assertStringContainsString('email_normalized', $sql);
        $this->assertStringNotContainsString('"email" =', $sql);
    }
}
