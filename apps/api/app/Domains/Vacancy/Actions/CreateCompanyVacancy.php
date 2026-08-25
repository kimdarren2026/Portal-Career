<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Exceptions\VacancyCompanyNotVerified;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Support\VacancyIdentifier;
use App\Domains\Vacancy\Support\VacancyVersionWriter;
use Illuminate\Support\Facades\DB;

/**
 * POST /companies/{company}/vacancies (FR-VAC-001, FR-VAC-003, FR-ONB-006).
 *
 * Creates at DRAFT with `vacancy_versions` version 1 as a full logical
 * snapshot. Ownership, status and creator identity are server-resolved; the
 * company row is locked so two concurrent creations cannot both pass a
 * verification gate that is changing (INV-002).
 */
final class CreateCompanyVacancy
{
    public function __construct(
        private readonly SyncVacancyChildren $children,
        private readonly VacancyVersionWriter $versions,
        private readonly AuditWriter $audit,
    ) {}

    /** @param array<string, mixed> $attributes Already validated and field-allow-listed. */
    public function execute(User $actor, Company $company, array $attributes): Vacancy
    {
        return DB::transaction(function () use ($actor, $company, $attributes): Vacancy {
            /** @var Company $locked */
            $locked = Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();

            // INV-002 / FR-ONB-006. Partnership is never part of this gate (INV-003).
            if ($locked->verification_status !== CompanyStatus::Verified) {
                throw new VacancyCompanyNotVerified('VACANCY_COMPANY_NOT_VERIFIED');
            }

            $requirements = $attributes['requirements'] ?? [];
            $questions = $attributes['screening_questions'] ?? [];
            unset($attributes['requirements'], $attributes['screening_questions']);

            $vacancy = new Vacancy();
            $vacancy->fill($attributes);
            // Ownership XOR (INV-018): company-owned, organizational_unit_id absent.
            $vacancy->forceFill([
                'vacancy_code' => VacancyIdentifier::code(),
                'slug' => VacancyIdentifier::slug((string) $attributes['title']),
                'ownership_type' => 'COMPANY',
                'company_id' => $locked->getKey(),
                'organizational_unit_id' => null,
                'created_by' => $actor->getKey(),
                'current_status' => VacancyStatus::Draft,
                'published_at' => null,
                'closed_at' => null,
                'suspended_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->save();

            $this->children->replaceRequirements($vacancy, $requirements);
            $this->children->replaceScreeningQuestions($vacancy, $questions);

            $this->versions->append($vacancy, $actor);
            $this->audit->record('vacancy_created', $actor, 'vacancy', (int) $vacancy->getKey(), [
                'company_id' => (int) $locked->getKey(),
                'vacancy_type' => $vacancy->vacancy_type?->value,
            ]);

            return $vacancy->refresh();
        });
    }
}
