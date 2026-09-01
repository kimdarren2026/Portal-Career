<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Enums\VacancyType;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Support\VacancyIdentifier;
use App\Domains\Vacancy\Support\VacancyVersionWriter;
use Illuminate\Support\Facades\DB;

/**
 * POST /hr/vacancies — create a campus vacancy (FR-HR-001, FR-HR-002,
 * FSD §8.4). Mirrors `CreateCompanyVacancy` exactly, minus the two things
 * that are company-only:
 *   - NO company row, NO `companies.verification_status` gate (FR-HR-001).
 *   - Ownership XOR (INV-018): `ownership_type = CAMPUS`, `company_id` NULL,
 *     `organizational_unit_id` set from the validated payload.
 *
 * Created at DRAFT with `vacancy_versions` version 1 as a full logical
 * snapshot (FR-VAC-007). `application_method` is forced to `IN_PORTAL`
 * (FR-HR-003, INV-005) — the request already rejects `EXTERNAL_ATS`.
 */
final class CreateCampusVacancy
{
    public function __construct(
        private readonly SyncVacancyChildren $children,
        private readonly VacancyVersionWriter $versions,
        private readonly AuditWriter $audit,
    ) {}

    /** @param array<string, mixed> $attributes Already validated and field-allow-listed. */
    public function execute(User $actor, array $attributes): Vacancy
    {
        return DB::transaction(function () use ($actor, $attributes): Vacancy {
            $organizationalUnitId = (int) $attributes['organizational_unit_id'];

            $requirements = $attributes['requirements'] ?? [];
            $questions = $attributes['screening_questions'] ?? [];
            unset($attributes['requirements'], $attributes['screening_questions'], $attributes['organizational_unit_id'], $attributes['vacancy_type']);

            $vacancy = new Vacancy();
            $vacancy->fill($attributes);
            $vacancy->forceFill([
                'vacancy_code' => VacancyIdentifier::code(),
                'slug' => VacancyIdentifier::slug((string) $attributes['title']),
                'vacancy_type' => VacancyType::CampusEmployment->value,
                'ownership_type' => 'CAMPUS',
                'company_id' => null,
                'organizational_unit_id' => $organizationalUnitId,
                'application_method' => 'IN_PORTAL',
                'external_ats_url' => null,
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
                'organizational_unit_id' => $organizationalUnitId,
                'vacancy_type' => VacancyType::CampusEmployment->value,
                'ownership_type' => 'CAMPUS',
            ]);

            return $vacancy->refresh();
        });
    }
}
