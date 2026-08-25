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

    protected function createVacancy(User $recruiter, Company $company, array $overrides = []): int
    {
        $response = $this->actingAs($recruiter)
            ->postJson("/companies/{$company->id}/vacancies", $this->payload($overrides))
            ->assertCreated();

        return (int) $response->json('data.id');
    }
}
