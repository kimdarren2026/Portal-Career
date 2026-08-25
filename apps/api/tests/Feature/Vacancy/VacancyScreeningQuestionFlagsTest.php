<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use Illuminate\Support\Facades\DB;

/**
 * PO decision VA-3 — a NEW screening question states `required` and `active`
 * explicitly. No server default exists on either path. PATCH of an existing
 * question stays a partial update.
 */
final class VacancyScreeningQuestionFlagsTest extends VacancyTestCase
{
    public function test_post_without_required_is_rejected(): void
    {
        [$recruiter, $id] = $this->draft('flag-no-required@example.test');

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'Missing required flag', 'question_type' => 'SHORT_TEXT',
            'active' => true, 'sort_order' => 0,
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields.required.0', 'The required field is required.');

        self::assertSame(0, DB::table('vacancy_screening_questions')->where('vacancy_id', $id)->count());
    }

    public function test_post_without_active_is_rejected(): void
    {
        [$recruiter, $id] = $this->draft('flag-no-active@example.test');

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'Missing active flag', 'question_type' => 'SHORT_TEXT',
            'required' => true, 'sort_order' => 0,
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields.active.0', 'The active field is required.');

        self::assertSame(0, DB::table('vacancy_screening_questions')->where('vacancy_id', $id)->count());
    }

    public function test_both_flags_are_accepted_in_every_explicit_combination(): void
    {
        [$recruiter, $id] = $this->draft('flag-combinations@example.test');

        foreach ([[false, false], [false, true], [true, false], [true, true]] as $index => [$required, $active]) {
            $this->actingAs($recruiter)->postJson("/vacancies/{$id}/screening-questions", [
                'question_text' => 'Combination '.$index, 'question_type' => 'YES_NO',
                'required' => $required, 'active' => $active, 'sort_order' => $index,
            ])->assertCreated()
                ->assertJsonPath('data.required', $required)
                ->assertJsonPath('data.active', $active);
        }

        $stored = DB::table('vacancy_screening_questions')->where('vacancy_id', $id)
            ->orderBy('sort_order')->get()->map(static fn ($row): array => [(bool) $row->required, (bool) $row->active])->all();
        self::assertSame([[false, false], [false, true], [true, false], [true, true]], $stored);
    }

    public function test_no_server_default_supplies_either_flag_when_both_are_missing(): void
    {
        [$recruiter, $id] = $this->draft('flag-none@example.test');

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'No flags at all', 'question_type' => 'LONG_TEXT', 'sort_order' => 0,
        ])->assertStatus(422)
            ->assertJsonPath('error.details.fields.required.0', 'The required field is required.')
            ->assertJsonPath('error.details.fields.active.0', 'The active field is required.');

        self::assertSame(0, DB::table('vacancy_screening_questions')->where('vacancy_id', $id)->count());
    }

    public function test_patch_may_omit_required_and_active_and_preserves_both(): void
    {
        [$recruiter, $id] = $this->draft('flag-patch@example.test');
        $questionId = (int) $this->actingAs($recruiter)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'Preserved flags', 'question_type' => 'SHORT_TEXT',
            'required' => true, 'active' => false, 'sort_order' => 0,
        ])->assertCreated()->json('data.id');

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}/screening-questions/{$questionId}", [
            'question_text' => 'Renamed only',
        ])->assertOk()
            ->assertJsonPath('data.required', true)
            ->assertJsonPath('data.active', false);

        $row = DB::table('vacancy_screening_questions')->where('id', $questionId)->first();
        self::assertTrue((bool) $row->required);
        self::assertFalse((bool) $row->active);
        self::assertSame('Renamed only', $row->question_text);
    }

    public function test_patch_still_changes_a_flag_when_it_is_supplied(): void
    {
        [$recruiter, $id] = $this->draft('flag-patch-change@example.test');
        $questionId = (int) $this->actingAs($recruiter)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'Deactivate me', 'question_type' => 'SHORT_TEXT',
            'required' => false, 'active' => true, 'sort_order' => 0,
        ])->assertCreated()->json('data.id');

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}/screening-questions/{$questionId}", ['active' => false])
            ->assertOk()->assertJsonPath('data.active', false)->assertJsonPath('data.required', false);
    }

    public function test_inline_creation_during_vacancy_create_requires_both_flags_per_question(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('flag-inline@example.test');

        $rejected = $this->actingAs($recruiter)->postJson("/companies/{$company->id}/vacancies", $this->payload([
            'screening_questions' => [
                ['question_text' => 'Complete', 'question_type' => 'SHORT_TEXT', 'required' => true, 'active' => true, 'sort_order' => 0],
                ['question_text' => 'Incomplete', 'question_type' => 'SHORT_TEXT', 'sort_order' => 1],
            ],
        ]))->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Per-question: the complete entry passes, the incomplete one is named.
        $fields = (array) $rejected->json('error.details.fields');
        self::assertArrayHasKey('screening_questions.1.required', $fields);
        self::assertArrayHasKey('screening_questions.1.active', $fields);
        self::assertArrayNotHasKey('screening_questions.0.required', $fields);
        self::assertArrayNotHasKey('screening_questions.0.active', $fields);

        self::assertSame(0, DB::table('vacancies')->count(), 'The whole create rolls back.');

        $id = $this->createVacancy($recruiter, $company, [
            'screening_questions' => [
                ['question_text' => 'Explicit false', 'question_type' => 'YES_NO', 'required' => false, 'active' => false, 'sort_order' => 0],
            ],
        ]);
        $row = DB::table('vacancy_screening_questions')->where('vacancy_id', $id)->first();
        self::assertFalse((bool) $row->required);
        self::assertFalse((bool) $row->active, 'An explicit false must not be overwritten by a default.');
    }

    /** @return array{\App\Domains\Identity\Models\User, int} */
    private function draft(string $email): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);

        return [$recruiter, $this->createVacancy($recruiter, $company)];
    }
}
