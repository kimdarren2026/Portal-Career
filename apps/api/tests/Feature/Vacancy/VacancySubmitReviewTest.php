<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Vacancy\Enums\ApplicationMethod;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Support\VacancySubmissionCompleteness;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST /vacancies/{vacancy}/submit-review — FR-VAC-004, FSD §8.3 and the B-5
 * completeness gate. Submit is an action: PENDING_REVIEW is stored and
 * SUBMITTED/DIAJUKAN never exist (INV-004).
 */
final class VacancySubmitReviewTest extends VacancyTestCase
{
    public function test_a_complete_draft_submits_to_pending_review_on_the_same_row(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('submit-draft@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'DRAFT');

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")
            ->assertOk()->assertJsonPath('data.current_status', 'PENDING_REVIEW');

        self::assertSame(1, DB::table('vacancies')->count(), 'Submit never creates a replacement vacancy.');
        self::assertSame('PENDING_REVIEW', DB::table('vacancies')->where('id', $id)->value('current_status'));
        self::assertSame(0, DB::table('vacancies')->whereIn('current_status', ['SUBMITTED', 'DIAJUKAN'])->count());

        $reviews = $this->reviewRows($id);
        self::assertCount(1, $reviews);
        self::assertSame('SUBMIT', $reviews[0]['action']);
        self::assertSame('DRAFT', $reviews[0]['from_status']);
        self::assertSame('PENDING_REVIEW', $reviews[0]['to_status']);
        self::assertSame(1, $this->auditCount('vacancy_submitted', $id));
        self::assertGreaterThan(0, $this->outboxCount($id));
    }

    public function test_revision_required_resubmits_the_same_row(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('submit-revision@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'REVISION_REQUIRED');

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")
            ->assertOk()->assertJsonPath('data.current_status', 'PENDING_REVIEW');

        self::assertSame(1, DB::table('vacancies')->count());
        self::assertSame('REVISION_REQUIRED', $this->reviewRows($id)[0]['from_status']);
    }

    public function test_an_incomplete_draft_is_refused_with_deterministic_missing_categories(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('submit-incomplete@example.test');
        // Only the create-contract minimum: every other category is absent.
        $id = $this->createVacancy($recruiter, $company, [
            'open_at' => now()->addDay()->toIso8601String(),
            'close_at' => now()->addDays(30)->toIso8601String(),
        ]);

        $response = $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")
            ->assertStatus(422)->assertJsonPath('error.code', 'VACANCY_PROFILE_INCOMPLETE');

        $missing = $response->json('error.details.missing');
        self::assertSame(
            ['qualifications', 'location', 'workplace_mode', 'minimum_education', 'experience_requirement'],
            $missing,
            'The list is ordered by category declaration, so it is reproducible.',
        );
        // Repeating the call returns the identical list.
        self::assertSame($missing, $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")
            ->json('error.details.missing'));

        self::assertSame('DRAFT', DB::table('vacancies')->where('id', $id)->value('current_status'));
        self::assertSame([], $this->reviewRows($id), 'A failed submit appends no review row.');
        self::assertSame(0, $this->auditCount('vacancy_submitted', $id));
        self::assertSame(0, $this->outboxCount($id));
    }

    #[DataProvider('missingCategoryProvider')]
    public function test_each_required_category_is_caught_individually(string $column, mixed $blank, string $category): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter("submit-{$category}@example.test");
        $id = $this->vacancyAt($recruiter, $company, 'DRAFT');
        DB::table('vacancies')->where('id', $id)->update([$column => $blank]);

        $response = $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")
            ->assertStatus(422)->assertJsonPath('error.code', 'VACANCY_PROFILE_INCOMPLETE');

        self::assertContains($category, (array) $response->json('error.details.missing'));
        self::assertSame('DRAFT', DB::table('vacancies')->where('id', $id)->value('current_status'));
        self::assertSame([], $this->reviewRows($id));
    }

    /**
     * NOT NULL string columns are emptied rather than nulled; the gate treats a
     * blank string as absent, which is the only way those categories can go
     * missing at all.
     *
     * @return array<string, array{string, mixed, string}>
     */
    public static function missingCategoryProvider(): array
    {
        return [
            'title' => ['title', '', 'title'],
            'description' => ['description', '', 'description'],
            'qualifications' => ['responsibilities', null, 'qualifications'],
            'employment type' => ['employment_type', '', 'employment_type'],
            'location' => ['location', null, 'location'],
            'work arrangement' => ['workplace_mode', null, 'workplace_mode'],
            'minimum education' => ['minimum_education', null, 'minimum_education'],
            'experience' => ['experience_requirement', null, 'experience_requirement'],
        ];
    }

    public function test_headcount_audience_and_method_cannot_go_missing_but_are_still_gated(): void
    {
        // These three are NOT NULL with CHECK constraints and are mandatory on
        // create, so the schema guarantees them. The gate still lists them, so a
        // future nullable representation cannot slip past unnoticed.
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('submit-structural@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'DRAFT');
        $row = DB::table('vacancies')->where('id', $id)->first();

        self::assertNotNull($row->openings_count);
        self::assertNotNull($row->target_audience);
        self::assertNotNull($row->application_method);

        $gate = app(\App\Domains\Vacancy\Support\VacancySubmissionCompleteness::class);
        $vacancy = \App\Domains\Vacancy\Models\Vacancy::findOrFail($id);
        self::assertSame([], $gate->missing($vacancy));

        $vacancy->openings_count = null;
        $vacancy->target_audience = null;
        $vacancy->application_method = null;
        self::assertSame(
            ['openings_count', 'target_audience', 'application_method'],
            array_values(array_intersect(
                ['openings_count', 'target_audience', 'application_method'],
                $gate->missing($vacancy),
            )),
        );
    }

    public function test_missing_dates_answer_their_own_frozen_code(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('submit-dates@example.test');

        foreach (['open_at', 'close_at'] as $column) {
            $id = $this->vacancyAt($recruiter, $company, 'DRAFT');
            DB::table('vacancies')->where('id', $id)->update([$column => null]);

            $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")
                ->assertStatus(422)->assertJsonPath('error.code', 'VACANCY_DATES_REQUIRED');
            self::assertSame('DRAFT', DB::table('vacancies')->where('id', $id)->value('current_status'));
        }
    }

    public function test_date_order_and_external_url_rules_are_guaranteed_structurally_and_still_gated(): void
    {
        // The schema enforces both rules — chk_vacancies_close_after_open and
        // chk_vacancies_external_url — so persisted state can never violate
        // them. The submit gate keeps its own checks as defence in depth, and
        // they are exercised here directly against the validator.
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('submit-structural-dates@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'DRAFT');
        $gate = app(VacancySubmissionCompleteness::class);

        $ordered = Vacancy::findOrFail($id);
        self::assertFalse($gate->closeBeforeOpen($ordered));
        self::assertFalse($gate->externalUrlMissing($ordered));
        self::assertFalse($gate->externalUrlInvalid($ordered));

        $reversed = Vacancy::findOrFail($id);
        $reversed->close_at = $reversed->open_at;
        self::assertTrue($gate->closeBeforeOpen($reversed), 'close_at equal to open_at is not after it.');

        $noUrl = Vacancy::findOrFail($id);
        $noUrl->application_method = ApplicationMethod::ExternalAts;
        $noUrl->external_ats_url = null;
        self::assertTrue($gate->externalUrlMissing($noUrl));

        $httpUrl = Vacancy::findOrFail($id);
        $httpUrl->application_method = ApplicationMethod::ExternalAts;
        $httpUrl->external_ats_url = 'http://ats.example.test/jobs/1';
        self::assertTrue($gate->externalUrlInvalid($httpUrl));

        // And an https external vacancy submits normally end to end.
        $external = $this->createVacancy($recruiter, $company, $this->submittablePayload([
            'application_method' => 'EXTERNAL_ATS',
            'external_ats_url' => 'https://ats.example.test/jobs/1',
        ]));
        $this->actingAs($recruiter)->postJson("/vacancies/{$external}/submit-review")
            ->assertOk()->assertJsonPath('data.current_status', 'PENDING_REVIEW');
    }

    public function test_optional_collections_and_salary_never_block_submission(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('submit-optional@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'DRAFT');

        // Zero study programs, zero skills, zero other requirements, zero
        // screening questions, and no salary at all.
        self::assertSame(0, DB::table('vacancy_requirements')->where('vacancy_id', $id)->count());
        self::assertSame(0, DB::table('vacancy_screening_questions')->where('vacancy_id', $id)->count());
        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertNull($row->salary_min);
        self::assertNull($row->salary_max);
        self::assertNull($row->salary_currency);

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")
            ->assertOk()->assertJsonPath('data.current_status', 'PENDING_REVIEW');
    }

    public function test_the_company_verified_gate_is_rechecked_at_submit(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('submit-gate@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'DRAFT');

        // Verified at create, suspended afterwards: submission must fail.
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'SUSPENDED']);
        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")
            ->assertForbidden()->assertJsonPath('error.code', 'VACANCY_COMPANY_NOT_VERIFIED');

        self::assertSame('DRAFT', DB::table('vacancies')->where('id', $id)->value('current_status'));
        self::assertSame([], $this->reviewRows($id));

        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'VERIFIED']);
        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")->assertOk();
    }

    public function test_submit_is_refused_from_a_status_that_does_not_allow_it(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('submit-transition@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PUBLISHED');

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")
            ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');
        self::assertSame([], $this->reviewRows($id));
    }

    public function test_an_idempotent_retry_replays_without_duplicating_any_side_effect(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('submit-idempotent@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'DRAFT');
        $key = 'submit-key-'.$id;

        $first = $this->actingAs($recruiter)->withHeaders(['Idempotency-Key' => $key])
            ->postJson("/vacancies/{$id}/submit-review")->assertOk();

        $replay = $this->actingAs($recruiter)->withHeaders(['Idempotency-Key' => $key])
            ->postJson("/vacancies/{$id}/submit-review")->assertOk()->assertHeader('Idempotency-Replayed', 'true');

        self::assertSame($first->json('data.current_status'), $replay->json('data.current_status'));
        self::assertCount(1, $this->reviewRows($id), 'Replay appends no second review row.');
        self::assertSame(1, $this->auditCount('vacancy_submitted', $id));
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'vacancy')
            ->where('related_object_id', $id)->where('title', 'SUBMIT')->count());
    }

    public function test_a_second_submission_without_the_key_is_refused_by_the_transition_rule(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('submit-second@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'DRAFT');

        $this->actingAs($recruiter)->withHeaders(['Idempotency-Key' => 'key-a'])
            ->postJson("/vacancies/{$id}/submit-review")->assertOk();

        // A different key is a logically separate submission; the source status
        // no longer permits it, so it is refused rather than replayed.
        $this->actingAs($recruiter)->withHeaders(['Idempotency-Key' => 'key-b'])
            ->postJson("/vacancies/{$id}/submit-review")
            ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');

        self::assertCount(1, $this->reviewRows($id));
    }

    public function test_a_non_owner_cannot_submit(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('submit-owner@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'DRAFT');
        $moderator = $this->moderator('submit-moderator@example.test');

        // Career Center reads the vacancy but never authors or submits it.
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/submit-review")
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        self::assertSame([], $this->reviewRows($id));
    }
}
