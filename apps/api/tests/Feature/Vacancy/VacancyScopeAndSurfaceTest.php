<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/** COMPANY_SCOPE isolation, the screening-question surface, and the phase boundary. */
final class VacancyScopeAndSurfaceTest extends VacancyTestCase
{
    public function test_one_company_never_sees_or_reaches_another_companys_vacancy(): void
    {
        [$recruiterA, $companyA] = $this->verifiedCompanyWithRecruiter('scope-a@example.test');
        [$recruiterB, $companyB] = $this->verifiedCompanyWithRecruiter('scope-b@example.test');
        $own = $this->createVacancy($recruiterA, $companyA, ['title' => 'Company A Role']);
        $foreign = $this->createVacancy($recruiterB, $companyB, ['title' => 'Company B Role']);

        $list = $this->actingAs($recruiterA)->getJson('/vacancies')->assertOk();
        $this->assertSame([$own], $list->json('data.items.*.id'));

        // Out of scope is absent from the query and 404 on direct read — never 403.
        foreach (["/vacancies/{$foreign}", "/vacancies/{$foreign}/versions", "/vacancies/{$foreign}/screening-questions"] as $path) {
            $this->actingAs($recruiterA)->getJson($path)->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        }
        $this->actingAs($recruiterA)->patchJson("/vacancies/{$foreign}", ['title' => 'Injected'])->assertNotFound();
        $this->assertSame('Company B Role', DB::table('vacancies')->where('id', $foreign)->value('title'));
    }

    public function test_inactive_and_revoked_memberships_grant_no_scope(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('membership@example.test');
        $id = $this->createVacancy($recruiter, $company);

        foreach ([['status' => 'INACTIVE'], ['revoked_at' => now()]] as $change) {
            DB::table('company_members')->where('company_id', $company->id)->where('user_id', $recruiter->id)
                ->update(['status' => 'ACTIVE', 'revoked_at' => null]);
            DB::table('company_members')->where('company_id', $company->id)->where('user_id', $recruiter->id)
                ->update($change);

            $this->actingAs($recruiter->fresh())->getJson('/vacancies')->assertOk()->assertJsonPath('data.items', []);
            $this->actingAs($recruiter->fresh())->getJson("/vacancies/{$id}")->assertNotFound();
            $this->actingAs($recruiter->fresh())->patchJson("/vacancies/{$id}", ['title' => 'Nope'])->assertNotFound();
        }
    }

    public function test_list_rejects_unsupported_filters_and_sorts(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('filters@example.test');
        $this->createVacancy($recruiter, $company);

        $this->actingAs($recruiter)->getJson('/vacancies?status=DRAFT')->assertOk();
        $this->actingAs($recruiter)->getJson('/vacancies?sort=title')->assertOk();
        $this->actingAs($recruiter)->getJson('/vacancies?sort=internal_note')
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->actingAs($recruiter)->getJson('/vacancies?secret_filter=1')
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_screening_question_get_post_and_patch_work(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('screening@example.test');
        $id = $this->createVacancy($recruiter, $company);

        $this->actingAs($recruiter)->getJson("/vacancies/{$id}/screening-questions")
            ->assertOk()->assertJsonPath('data.items', []);

        $created = $this->actingAs($recruiter)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'Do you have a work permit?', 'question_type' => 'YES_NO',
            'required' => true, 'active' => true, 'sort_order' => 0,
        ])->assertCreated()->assertJsonPath('data.active', true);
        $questionId = (int) $created->json('data.id');

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}/screening-questions/{$questionId}", ['active' => false])
            ->assertOk()->assertJsonPath('data.active', false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'vacancy_screening_question_changed', 'object_id' => $id]);
    }

    public function test_single_choice_options_rule_is_enforced_both_ways(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('options@example.test');
        $id = $this->createVacancy($recruiter, $company);

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'Preferred site?', 'question_type' => 'SINGLE_CHOICE', 'sort_order' => 0,
            'required' => false, 'active' => true,
        ])->assertStatus(422);

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'Years?', 'question_type' => 'NUMBER', 'sort_order' => 0,
            'required' => false, 'active' => true, 'options_definition' => ['a', 'b'],
        ])->assertStatus(422);

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'Preferred site?', 'question_type' => 'SINGLE_CHOICE', 'sort_order' => 0,
            'required' => false, 'active' => true, 'options_definition' => ['Jakarta', 'Bandung'],
        ])->assertCreated();
    }

    public function test_screening_questions_are_reachable_only_through_their_own_vacancy(): void
    {
        [$recruiterA, $companyA] = $this->verifiedCompanyWithRecruiter('q-a@example.test');
        [$recruiterB, $companyB] = $this->verifiedCompanyWithRecruiter('q-b@example.test');
        $vacancyA = $this->createVacancy($recruiterA, $companyA);
        $vacancyB = $this->createVacancy($recruiterB, $companyB);

        $foreignQuestion = (int) $this->actingAs($recruiterB)->postJson("/vacancies/{$vacancyB}/screening-questions", [
            'question_text' => 'B question', 'question_type' => 'SHORT_TEXT', 'sort_order' => 0,
            'required' => false, 'active' => true,
        ])->assertCreated()->json('data.id');

        // INV-019: a question from another vacancy can never be reached here.
        $this->actingAs($recruiterA)->patchJson("/vacancies/{$vacancyA}/screening-questions/{$foreignQuestion}", ['active' => false])
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_screening_questions_are_locked_when_the_vacancy_is_not_editable(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('q-locked@example.test');
        $id = $this->createVacancy($recruiter, $company);
        DB::table('vacancies')->where('id', $id)->update(['current_status' => 'PUBLISHED']);

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'Late question', 'question_type' => 'SHORT_TEXT', 'sort_order' => 0,
            'required' => false, 'active' => true,
        ])->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_NOT_EDITABLE');
    }

    public function test_no_delete_route_and_no_lifecycle_surface_exists_yet(): void
    {
        $registered = collect(Route::getRoutes())->map(
            static fn ($route): string => strtoupper(implode('|', $route->methods())).' '.$route->uri(),
        );

        // The contract defines no DELETE for screening questions.
        $this->assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'DELETE') && str_contains($r, 'screening-questions')));

        // Nothing beyond authoring is routed: each depends on an unresolved decision.
        foreach (['submit-review', 'approve', 'request-revision', 'reject', 'publish', 'close', 'suspend', 'restore', 'moderation-history'] as $absent) {
            $this->assertFalse(
                $registered->contains(fn (string $r): bool => str_contains($r, $absent)),
                "No route may exist for {$absent} in this phase.",
            );
        }

        $this->assertSame(8, $registered->filter(
            static fn (string $r): bool => str_contains($r, 'vacanc'),
        )->count(), 'Exactly the eight authoring routes.');
    }
}
