<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Exceptions\VacancyNotEditable;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Models\VacancyScreeningQuestion;
use Illuminate\Support\Facades\DB;

/**
 * POST/PATCH /vacancies/{vacancy}/screening-questions (FR-VAC-003, FR-HR-002).
 *
 * There is deliberately no delete path: a question that already has answers can
 * never be hard-deleted, and deactivation via `active = false` covers every
 * case including a never-answered question (`API_SIZE_REVIEW.md` Q-1). A
 * question belongs to exactly one vacancy (INV-019).
 */
final class SaveScreeningQuestion
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, Vacancy $vacancy, array $attributes): VacancyScreeningQuestion
    {
        return DB::transaction(function () use ($actor, $vacancy, $attributes): VacancyScreeningQuestion {
            $locked = $this->lockEditable($vacancy);

            $question = new VacancyScreeningQuestion();
            // `required` and `active` are NOT NULL in the frozen schema and the
            // contract leaves them optional on the request. A new question is
            // created active and not-required unless the caller says otherwise;
            // the same defaults the inline create path applies.
            $question->fill($attributes + ['required' => false, 'active' => true]);
            $question->forceFill(['vacancy_id' => $locked->getKey()])->save();

            $this->audit->record('vacancy_screening_question_changed', $actor, 'vacancy', (int) $locked->getKey(), [
                'screening_question_id' => (int) $question->getKey(),
                'change' => 'created',
            ]);

            return $question;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Vacancy $vacancy, VacancyScreeningQuestion $question, array $attributes): VacancyScreeningQuestion
    {
        return DB::transaction(function () use ($actor, $vacancy, $question, $attributes): VacancyScreeningQuestion {
            $locked = $this->lockEditable($vacancy);

            /** @var VacancyScreeningQuestion $lockedQuestion */
            $lockedQuestion = VacancyScreeningQuestion::query()->whereKey($question->getKey())->lockForUpdate()->firstOrFail();
            $lockedQuestion->fill($attributes);
            $changed = array_keys($lockedQuestion->getDirty());
            $lockedQuestion->save();

            $this->audit->record('vacancy_screening_question_changed', $actor, 'vacancy', (int) $locked->getKey(), [
                'screening_question_id' => (int) $lockedQuestion->getKey(),
                'change' => 'updated',
                'fields' => $changed,
            ]);

            return $lockedQuestion->refresh();
        });
    }

    private function lockEditable(Vacancy $vacancy): Vacancy
    {
        /** @var Vacancy $locked */
        $locked = Vacancy::query()->whereKey($vacancy->getKey())->lockForUpdate()->firstOrFail();

        if (! $locked->current_status->isCompanyEditable()) {
            throw new VacancyNotEditable('VACANCY_NOT_EDITABLE');
        }

        return $locked;
    }
}
