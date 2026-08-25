<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Identity\IdentityTestCase;

abstract class VacancyTestCase extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /** @return array{User, Company} */
    protected function verifiedCompanyWithRecruiter(string $email): array
    {
        return $this->companyWithRecruiter($email, CompanyStatus::Verified);
    }

    /** @return array{User, Company} */
    protected function companyWithRecruiter(string $email, CompanyStatus $status): array
    {
        $recruiter = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($recruiter, RoleCode::CompanyRecruiter);

        $this->sequence++;
        $id = DB::table('companies')->insertGetId([
            'name' => 'Vacancy Co '.$this->sequence,
            'normalized_name' => 'vacancy co '.$this->sequence,
            'verification_status' => $status->value,
            'created_by' => $recruiter->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('company_members')->insert([
            'company_id' => $id, 'user_id' => $recruiter->id,
            'company_role' => 'COMPANY_ADMIN', 'status' => 'ACTIVE', 'joined_at' => now(),
        ]);

        return [$recruiter, Company::findOrFail($id)];
    }

    /** The smallest payload the create contract accepts; DRAFT may be incomplete. */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'vacancy_type' => 'COMPANY_EMPLOYMENT',
            'title' => 'Backend Engineer',
            'description' => 'Build and maintain services.',
            'employment_type' => 'FULL_TIME',
            'openings_count' => 2,
            'target_audience' => 'PUBLIC',
            'application_method' => 'IN_PORTAL',
        ], $overrides);
    }

    /** A vacancy whose persisted state satisfies every B-5 category. */
    protected function submittablePayload(array $overrides = []): array
    {
        return $this->payload(array_merge([
            'description' => 'Membangun dan memelihara layanan backend.',
            'responsibilities' => 'S1 Teknik Informatika, menguasai PHP dan PostgreSQL.',
            'location' => 'Jakarta Selatan',
            'workplace_mode' => 'HYBRID',
            'minimum_education' => 'S1',
            'experience_requirement' => 'Minimal 2 tahun',
            'open_at' => now()->addDay()->toIso8601String(),
            'close_at' => now()->addDays(30)->toIso8601String(),
        ], $overrides));
    }

    /** Creates a vacancy that can be submitted, then moves it to the wanted status. */
    protected function vacancyAt(User $recruiter, Company $company, string $status, array $overrides = []): int
    {
        $id = $this->createVacancy($recruiter, $company, $this->submittablePayload($overrides));
        if ($status !== 'DRAFT') {
            DB::table('vacancies')->where('id', $id)->update(['current_status' => $status]);
        }

        return $id;
    }

    /** @return array{User, Company} A moderator with no membership of any company. */
    protected function moderator(string $email, RoleCode $role = RoleCode::CareerCenterStaff): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, $role);

        return $user;
    }

    protected function reviewRows(int $vacancyId): array
    {
        return DB::table('vacancy_moderation_reviews')->where('vacancy_id', $vacancyId)
            ->orderBy('id')->get()->map(static fn ($r): array => (array) $r)->all();
    }

    protected function auditCount(string $action, int $vacancyId): int
    {
        return DB::table('audit_logs')->where('action', $action)
            ->where('object_type', 'vacancy')->where('object_id', $vacancyId)->count();
    }

    protected function outboxCount(int $vacancyId): int
    {
        return DB::table('email_outbox')->where('related_object_type', 'vacancy')
            ->where('related_object_id', $vacancyId)->count();
    }

    /** Master-data skill for typed SKILL requirements. */
    protected function skillId(): int
    {
        $this->sequence++;

        return (int) DB::table('skills')->insertGetId([
            'name' => 'Skill '.$this->sequence, 'normalized_name' => 'skill-'.$this->sequence, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Membership row for an existing user on an existing company. */
    protected function addMember(int $companyId, User $user, string $companyRole = 'COMPANY_ADMIN', string $status = 'ACTIVE'): void
    {
        DB::table('company_members')->insert([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'company_role' => $companyRole,
            'status' => $status,
            'joined_at' => now(),
            'revoked_at' => $status === 'ACTIVE' ? null : now(),
        ]);
    }

    /** @return list<array<string, mixed>> Current requirement rows, ordered as stored. */
    protected function requirementRows(int $vacancyId): array
    {
        return DB::table('vacancy_requirements')->where('vacancy_id', $vacancyId)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(static fn ($row): array => (array) $row)->all();
    }

    protected function latestVersion(int $vacancyId): int
    {
        return (int) DB::table('vacancy_versions')->where('vacancy_id', $vacancyId)->max('version_number');
    }

    /** @return array<string, mixed> */
    protected function snapshotOf(int $vacancyId, int $versionNumber): array
    {
        $snapshot = DB::table('vacancy_versions')->where('vacancy_id', $vacancyId)
            ->where('version_number', $versionNumber)->value('snapshot');

        return json_decode((string) $snapshot, true, flags: JSON_THROW_ON_ERROR);
    }

    protected function createVacancy(User $recruiter, Company $company, array $overrides = []): int
    {
        $response = $this->actingAs($recruiter)
            ->postJson("/companies/{$company->id}/vacancies", $this->payload($overrides))
            ->assertCreated();

        return (int) $response->json('data.id');
    }
}
