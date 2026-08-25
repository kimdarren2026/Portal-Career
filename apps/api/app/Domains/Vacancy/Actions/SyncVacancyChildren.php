<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Vacancy\Enums\RequirementType;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Models\VacancyRequirement;
use App\Domains\Vacancy\Models\VacancyScreeningQuestion;

/**
 * Writes vacancy child collections. The caller supplies the transaction, so a
 * child failure rolls the parent and its version back with it.
 *
 * `replaceRequirements` is a **full collection replacement** (PO decision
 * VA-1): the stored rows are removed and the supplied array becomes the whole
 * collection, so an empty array clears it. There is no merge-by-id, because
 * the decision is replacement and no frozen representation carries a stable
 * per-row client identity.
 */
final class SyncVacancyChildren
{
    /** @param list<array<string, mixed>> $requirements */
    public function replaceRequirements(Vacancy $vacancy, array $requirements): void
    {
        VacancyRequirement::query()->where('vacancy_id', $vacancy->getKey())->delete();

        foreach (array_values($requirements) as $index => $requirement) {
            $type = RequirementType::from((string) $requirement['requirement_type']);

            $row = new VacancyRequirement();
            $row->fill([
                'requirement_type' => $type->value,
                // Exactly one typed value per requirement_type
                // (chk_vacancy_requirements_typed_value).
                $type->valueColumn() => $requirement[$type->valueColumn()] ?? null,
                'note' => $requirement['note'] ?? null,
                'required' => (bool) ($requirement['required'] ?? true),
                'sort_order' => (int) ($requirement['sort_order'] ?? $index),
            ]);
            $row->forceFill(['vacancy_id' => $vacancy->getKey()])->save();
        }
    }

    /** @param list<array<string, mixed>> $questions */
    public function replaceScreeningQuestions(Vacancy $vacancy, array $questions): void
    {
        foreach (array_values($questions) as $index => $question) {
            $row = new VacancyScreeningQuestion();
            $row->fill([
                'question_text' => $question['question_text'],
                'question_type' => $question['question_type'],
                // VA-3: both flags are mandatory on a new question, so the
                // request has already supplied them. No default is applied.
                'required' => (bool) $question['required'],
                'options_definition' => $question['options_definition'] ?? null,
                'sort_order' => (int) ($question['sort_order'] ?? $index),
                'active' => (bool) $question['active'],
            ]);
            $row->forceFill(['vacancy_id' => $vacancy->getKey()])->save();
        }
    }
}
