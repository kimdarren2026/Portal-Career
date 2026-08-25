<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use Illuminate\Support\Facades\DB;

/**
 * PO decision VA-1 — `requirements[]` on PATCH /vacancies/{vacancy}.
 *
 * Omitted preserves, present replaces the complete collection, `[]` clears.
 * The synchronization is atomic with the concurrency check, the parent write,
 * the version append and the audit entry.
 */
final class VacancyRequirementSyncTest extends VacancyTestCase
{
    public function test_patch_without_requirements_preserves_the_existing_collection(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('req-keep@example.test');
        $skill = $this->skillId();
        $id = $this->createVacancy($recruiter, $company, [
            'requirements' => [
                ['requirement_type' => 'SKILL', 'skill_id' => $skill, 'required' => true, 'sort_order' => 0],
                ['requirement_type' => 'EDUCATION', 'education_level' => 'S1', 'required' => false, 'sort_order' => 1],
            ],
        ]);
        $before = $this->requirementRows($id);
        self::assertCount(2, $before);

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", ['title' => 'Renamed Only'])
            ->assertOk()->assertJsonPath('data.title', 'Renamed Only');

        // Same rows, same identities: nothing was rewritten.
        self::assertSame($before, $this->requirementRows($id));
    }

    public function test_patch_with_requirements_replaces_the_complete_collection(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('req-replace@example.test');
        $id = $this->createVacancy($recruiter, $company, [
            'requirements' => [['requirement_type' => 'EDUCATION', 'education_level' => 'D3', 'required' => true, 'sort_order' => 0]],
        ]);
        $skill = $this->skillId();

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", [
            'requirements' => [
                ['requirement_type' => 'SKILL', 'skill_id' => $skill, 'required' => true, 'sort_order' => 0],
                ['requirement_type' => 'EXPERIENCE', 'minimum_years_experience' => 3, 'required' => false, 'sort_order' => 1],
            ],
        ])->assertOk();

        $rows = $this->requirementRows($id);
        self::assertCount(2, $rows, 'Replacement, not merge: the previous EDUCATION row is gone.');
        self::assertSame(['SKILL', 'EXPERIENCE'], array_column($rows, 'requirement_type'));
        self::assertSame(0, DB::table('vacancy_requirements')->where('vacancy_id', $id)
            ->where('requirement_type', 'EDUCATION')->count());
    }

    public function test_patch_with_an_empty_array_clears_every_requirement(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('req-clear@example.test');
        $id = $this->createVacancy($recruiter, $company, [
            'requirements' => [['requirement_type' => 'EDUCATION', 'education_level' => 'S1', 'required' => true, 'sort_order' => 0]],
        ]);

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", ['requirements' => []])->assertOk();

        self::assertSame([], $this->requirementRows($id));
    }

    public function test_a_requirements_only_patch_is_accepted_and_appends_exactly_one_version(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('req-only@example.test');
        $id = $this->createVacancy($recruiter, $company);
        self::assertSame(1, $this->latestVersion($id));

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", [
            'requirements' => [['requirement_type' => 'OTHER_QUALIFICATION', 'value_text' => 'SIM A', 'required' => true, 'sort_order' => 0]],
        ])->assertOk()->assertJsonPath('data.current_version', 2);

        self::assertSame(2, DB::table('vacancy_versions')->where('vacancy_id', $id)->count());
        self::assertSame(2, $this->latestVersion($id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'vacancy_updated', 'object_id' => $id]);
    }

    public function test_replacement_snapshot_carries_the_post_sync_collection(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('req-snap@example.test');
        $id = $this->createVacancy($recruiter, $company, [
            'requirements' => [['requirement_type' => 'EDUCATION', 'education_level' => 'SMA', 'required' => true, 'sort_order' => 0]],
        ]);

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", [
            'requirements' => [['requirement_type' => 'EXPERIENCE', 'minimum_years_experience' => 5, 'required' => true, 'sort_order' => 0]],
        ])->assertOk();

        $snapshot = $this->snapshotOf($id, 2);
        self::assertCount(1, $snapshot['requirements']);
        self::assertSame('EXPERIENCE', $snapshot['requirements'][0]['requirement_type']);
        self::assertSame(5, $snapshot['requirements'][0]['minimum_years_experience']);
    }

    public function test_omitted_requirements_snapshot_carries_the_preserved_collection(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('req-snap-keep@example.test');
        $id = $this->createVacancy($recruiter, $company, [
            'requirements' => [['requirement_type' => 'EDUCATION', 'education_level' => 'S2', 'required' => true, 'sort_order' => 0]],
        ]);

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", ['title' => 'Untouched Requirements'])->assertOk();

        $snapshot = $this->snapshotOf($id, 2);
        self::assertCount(1, $snapshot['requirements']);
        self::assertSame('EDUCATION', $snapshot['requirements'][0]['requirement_type']);
        self::assertSame('S2', $snapshot['requirements'][0]['education_level']);
    }

    public function test_an_invalid_replacement_item_rolls_back_the_parent_the_collection_and_the_version(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('req-invalid@example.test');
        $id = $this->createVacancy($recruiter, $company, [
            'title' => 'Original Title',
            'requirements' => [['requirement_type' => 'EDUCATION', 'education_level' => 'S1', 'required' => true, 'sort_order' => 0]],
        ]);
        $before = $this->requirementRows($id);

        // EXPERIENCE without minimum_years_experience breaks the typed-value rule.
        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", [
            'title' => 'Should Not Persist',
            'requirements' => [['requirement_type' => 'EXPERIENCE', 'required' => true, 'sort_order' => 0]],
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        self::assertSame('Original Title', DB::table('vacancies')->where('id', $id)->value('title'));
        self::assertSame($before, $this->requirementRows($id));
        self::assertSame(1, DB::table('vacancy_versions')->where('vacancy_id', $id)->count());
    }

    public function test_a_stale_if_match_changes_nothing_and_never_touches_requirements(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('req-stale@example.test');
        $skill = $this->skillId();
        $id = $this->createVacancy($recruiter, $company, [
            'title' => 'Stable Title',
            'requirements' => [['requirement_type' => 'SKILL', 'skill_id' => $skill, 'required' => true, 'sort_order' => 0]],
        ]);
        $before = $this->requirementRows($id);

        $this->actingAs($recruiter)->withHeaders(['If-Match' => '"99"'])
            ->patchJson("/vacancies/{$id}", ['title' => 'Stale Write', 'requirements' => []])
            ->assertStatus(409)->assertJsonPath('error.code', 'STALE_VERSION');

        self::assertSame('Stable Title', DB::table('vacancies')->where('id', $id)->value('title'));
        self::assertSame($before, $this->requirementRows($id), 'A rejected edit must never clear the collection.');
        self::assertSame(1, DB::table('vacancy_versions')->where('vacancy_id', $id)->count());
    }

    public function test_a_matching_if_match_synchronises_requirements_and_allocates_the_next_version(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('req-ifmatch@example.test');
        $id = $this->createVacancy($recruiter, $company);

        $this->actingAs($recruiter)->withHeaders(['If-Match' => '"1"'])
            ->patchJson("/vacancies/{$id}", [
                'requirements' => [['requirement_type' => 'CERTIFICATION', 'value_text' => 'AWS SAA', 'required' => true, 'sort_order' => 0]],
            ])->assertOk()->assertJsonPath('data.current_version', 2);

        self::assertCount(1, $this->requirementRows($id));
        self::assertSame(2, $this->latestVersion($id));

        // The next edit must see version 2, proving allocation stayed sequential
        // across a requirements synchronization.
        $this->actingAs($recruiter)->withHeaders(['If-Match' => '"2"'])
            ->patchJson("/vacancies/{$id}", ['requirements' => []])->assertOk()
            ->assertJsonPath('data.current_version', 3);
        self::assertSame([], $this->requirementRows($id));
    }

    public function test_patch_accepts_requirements_but_not_screening_questions(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('req-surface@example.test');
        $id = $this->createVacancy($recruiter, $company);

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", [
            'requirements' => [['requirement_type' => 'EDUCATION', 'education_level' => 'S1', 'required' => true, 'sort_order' => 0]],
        ])->assertOk()->assertJsonPath('data.requirements.0.requirement_type', 'EDUCATION');

        // VA-2: unsupported field, ordinary convention, no new error code.
        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", [
            'screening_questions' => [['question_text' => 'Sneaky', 'question_type' => 'SHORT_TEXT', 'required' => true, 'active' => true, 'sort_order' => 0]],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields.screening_questions.0', 'Field tidak didukung pada operasi ini.');

        self::assertSame(0, DB::table('vacancy_screening_questions')->where('vacancy_id', $id)->count());
        self::assertSame(2, $this->latestVersion($id), 'The rejected screening payload appended no version.');
    }
}
