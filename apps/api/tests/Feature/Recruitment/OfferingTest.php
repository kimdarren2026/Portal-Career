<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Offering Foundation v1 — create, update, send, accept, reject.
 * OF-1, OF-2, RC-1 all approved and CLOSED. Offer revoke, expiry scheduler,
 * salary fields, document upload, Evaluation coupling, automatic Outcome
 * creation, Campus, and External Apply all remain deliberately
 * unimplemented.
 */
final class OfferingTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Route inventory
    // ---------------------------------------------------------------

    public function test_route_inventory_matches_frozen_surface(): void
    {
        $registered = collect(Route::getRoutes())->map(
            static fn ($route): string => strtoupper(implode('|', $route->methods())).' '.$route->uri(),
        );

        self::assertTrue($registered->contains('POST applications/{application}/offers'));
        self::assertTrue($registered->contains('PATCH offers/{offer}'));
        self::assertTrue($registered->contains('POST offers/{offer}/send'));
        self::assertTrue($registered->contains('POST offers/{offer}/accept'));
        self::assertTrue($registered->contains('POST offers/{offer}/reject'));

        foreach (['revoke', 'withdraw', 'resend', 'expire', 'bulk', 'recruitment-outcomes'] as $absent) {
            self::assertFalse(
                $registered->contains(fn (string $r): bool => str_contains($r, $absent) && str_contains($r, 'offer')),
                "No route may exist for {$absent} in this milestone.",
            );
        }
        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'GET') && str_contains($r, 'offers')));
        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'DELETE') && str_contains($r, 'offer')));
        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'api/v1') && str_contains($r, 'offer')));
    }

    // ---------------------------------------------------------------
    // Recruiter authorization
    // ---------------------------------------------------------------

    public function test_recruiter_admin_and_super_admin_can_create_update_send(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('of-write-actors@example.test');
        $recruiter = $this->recruiterMember($company->id, 'of-write-actors-r@example.test');
        $superAdmin = $this->moderator('of-write-actors-sa@example.test', RoleCode::SuperAdmin);

        foreach ([$admin, $recruiter, $superAdmin] as $actor) {
            [$candidate] = $this->candidate('of-write-actors-c-'.$actor->id.'@example.test');
            $applicationId = $this->submitApplication($candidate, $vacancyId);

            $created = $this->actingAs($actor)->postJson("/applications/{$applicationId}/offers", $this->offerPayload())
                ->assertCreated()->assertJsonPath('data.status', 'DRAFT');
            $offerId = (int) $created->json('data.id');

            $this->actingAs($actor)->patchJson("/offers/{$offerId}", ['note' => 'Updated note'])->assertOk();
            $this->actingAs($actor)
                ->postJson("/offers/{$offerId}/send", [], ['Idempotency-Key' => 'of-send-'.uniqid('', true)])
                ->assertOk()->assertJsonPath('data.status', 'SENT');
        }
    }

    public function test_revoked_membership_cannot_create(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('of-revoked@example.test');
        [$candidate] = $this->candidate('of-revoked-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        DB::table('company_members')->where('company_id', $company->id)->where('user_id', $admin->id)
            ->update(['status' => 'INACTIVE']);

        $this->actingAs($admin->fresh())->postJson("/applications/{$applicationId}/offers", $this->offerPayload())
            ->assertNotFound();
    }

    public function test_cross_company_recruiter_gets_enumeration_safe_404(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-cross-a@example.test');
        [, $companyB] = $this->verifiedCompanyWithRecruiter('of-cross-b@example.test');
        [$candidate] = $this->candidate('of-cross-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $recruiterB = $this->recruiterMember($companyB->id, 'of-cross-recruiter-b@example.test');

        $this->actingAs($recruiterB)->postJson("/applications/{$applicationId}/offers", $this->offerPayload())
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        $offerId = $this->createOffer($admin, $applicationId);
        $this->actingAs($recruiterB)->patchJson("/offers/{$offerId}", ['note' => 'x'])
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_career_center_selector_auditor_and_candidate_denied_recruiter_write(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-deny-write@example.test');
        [$candidate] = $this->candidate('of-deny-write-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerId = $this->createOffer($admin, $applicationId);

        $careerCenter = $this->moderator('of-deny-write-cc@example.test', RoleCode::CareerCenterStaff);
        $selector = $this->moderator('of-deny-write-sel@example.test', RoleCode::Selector);
        $auditor = $this->moderator('of-deny-write-aud@example.test', RoleCode::Auditor);

        foreach ([$careerCenter, $selector, $auditor, $candidate] as $actor) {
            $this->actingAs($actor)->postJson("/applications/{$applicationId}/offers", $this->offerPayload())
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
            $this->actingAs($actor)->patchJson("/offers/{$offerId}", ['note' => 'x'])
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
            $this->actingAs($actor)->postJson("/offers/{$offerId}/send", [])
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }
    }

    // ---------------------------------------------------------------
    // Candidate authorization (OF-2)
    // ---------------------------------------------------------------

    public function test_candidate_own_accept_and_reject_no_proxy(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-candidate-auth@example.test');
        [$candidate] = $this->candidate('of-candidate-auth-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerId = $this->sendOffer($admin, $applicationId);

        $recruiter = $this->recruiterMember($this->companyOf($vacancyId), 'of-candidate-auth-r@example.test');
        $superAdmin = $this->moderator('of-candidate-auth-sa@example.test', RoleCode::SuperAdmin);
        $careerCenter = $this->moderator('of-candidate-auth-cc@example.test', RoleCode::CareerCenterStaff);

        foreach ([$admin, $recruiter, $superAdmin, $careerCenter] as $actor) {
            $this->actingAs($actor)->postJson("/offers/{$offerId}/accept", [])
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }

        [$otherCandidate] = $this->candidate('of-candidate-auth-other@example.test');
        $this->actingAs($otherCandidate)->postJson("/offers/{$offerId}/accept", [])
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [])->assertOk();
    }

    // ---------------------------------------------------------------
    // OF-1 — status independence
    // ---------------------------------------------------------------

    public function test_of1_create_update_send_never_mutate_application_status(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of1-independence@example.test');
        [$candidate] = $this->candidate('of1-independence-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $offerId = (int) $this->actingAs($admin)->postJson("/applications/{$applicationId}/offers", $this->offerPayload())
            ->assertCreated()->json('data.id');
        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));

        $this->actingAs($admin)->patchJson("/offers/{$offerId}", ['note' => 'x'])->assertOk();
        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));

        $this->actingAs($admin)
            ->postJson("/offers/{$offerId}/send", [], ['Idempotency-Key' => 'of1-'.uniqid('', true)])
            ->assertOk();
        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
        self::assertNull(DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
    }

    public function test_of1_accept_sets_hired_reject_does_not_mutate_status(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of1-accept@example.test');
        [$candidateA] = $this->candidate('of1-accept-a@example.test');
        $applicationA = $this->submitApplication($candidateA, $vacancyId);
        $offerA = $this->sendOffer($admin, $applicationA);

        $this->actingAs($candidateA)->postJson("/offers/{$offerA}/accept", [])
            ->assertOk()->assertJsonPath('data.status', 'ACCEPTED');
        self::assertSame('HIRED', DB::table('applications')->where('id', $applicationA)->value('current_status'));
        self::assertNotNull(DB::table('applications')->where('id', $applicationA)->value('hired_at'));
        self::assertSame(1, DB::table('application_status_histories')->where('application_id', $applicationA)->where('event_type', 'OFFER_ACCEPTED')->count());

        [$candidateB] = $this->candidate('of1-accept-b@example.test');
        $applicationB = $this->submitApplication($candidateB, $vacancyId);
        $offerB = $this->sendOffer($admin, $applicationB);

        $this->actingAs($candidateB)->postJson("/offers/{$offerB}/reject", ['rejection_reason' => 'Not interested'])
            ->assertOk()->assertJsonPath('data.status', 'REJECTED');
        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationB)->value('current_status'));
    }

    // ---------------------------------------------------------------
    // RC-1 — RA-2
    // ---------------------------------------------------------------

    public function test_rc1_create_update_send_gated_accept_reject_exempt(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('of-rc1@example.test');
        [$candidate] = $this->candidate('of-rc1-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerId = $this->createOffer($admin, $applicationId);

        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'SUSPENDED']);
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/offers", $this->offerPayload())
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
        $this->actingAs($admin)->patchJson("/offers/{$offerId}", ['note' => 'x'])
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
        $this->actingAs($admin)->postJson("/offers/{$offerId}/send", [])
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'PUBLISHED']);

        // Send while processable, then suspend — accept must still succeed (RA-2 exempt).
        $sentId = $this->sendOffer($admin, $applicationId, $offerId);
        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'SUSPENDED']);
        $this->actingAs($candidate)->postJson("/offers/{$sentId}/accept", [])->assertOk();
    }

    public function test_rc1_super_admin_does_not_bypass(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-rc1-super@example.test');
        [$candidate] = $this->candidate('of-rc1-super-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'SUSPENDED']);

        $superAdmin = $this->moderator('of-rc1-super-admin@example.test', RoleCode::SuperAdmin);
        $this->actingAs($superAdmin)->postJson("/applications/{$applicationId}/offers", $this->offerPayload())
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
    }

    // ---------------------------------------------------------------
    // Terminal application (create)
    // ---------------------------------------------------------------

    public function test_terminal_application_cannot_receive_new_offer(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-terminal@example.test');
        [$candidate] = $this->candidate('of-terminal-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_status' => 'REJECTED']);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/offers", $this->offerPayload())
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_TERMINAL');
    }

    // ---------------------------------------------------------------
    // Offer lifecycle
    // ---------------------------------------------------------------

    public function test_create_default_draft_and_send_now(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-lifecycle@example.test');
        [$candidate] = $this->candidate('of-lifecycle-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $draft = $this->actingAs($admin)->postJson("/applications/{$applicationId}/offers", $this->offerPayload())
            ->assertCreated();
        self::assertSame('DRAFT', $draft->json('data.status'));
        self::assertNull($draft->json('data.sent_at'));

        [$candidate2] = $this->candidate('of-lifecycle-c2@example.test');
        $applicationId2 = $this->submitApplication($candidate2, $vacancyId);
        $sentNow = $this->actingAs($admin)->postJson("/applications/{$applicationId2}/offers", $this->offerPayload(['send_now' => true]))
            ->assertCreated();
        self::assertSame('SENT', $sentNow->json('data.status'));
        self::assertNotNull($sentNow->json('data.sent_at'));
        self::assertSame(1, DB::table('audit_logs')->where('action', 'offer_created')->where('object_id', $sentNow->json('data.id'))->count());
        self::assertSame(1, DB::table('audit_logs')->where('action', 'offer_sent')->where('object_id', $sentNow->json('data.id'))->count());
    }

    public function test_update_only_allowed_while_draft(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-update-lifecycle@example.test');
        [$candidate] = $this->candidate('of-update-lifecycle-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerId = $this->sendOffer($admin, $applicationId);

        $this->actingAs($admin)->patchJson("/offers/{$offerId}", ['note' => 'too late'])
            ->assertStatus(409)->assertJsonPath('error.code', 'OFFER_INVALID_TRANSITION');
    }

    public function test_send_requires_draft(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-send-lifecycle@example.test');
        [$candidate] = $this->candidate('of-send-lifecycle-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerId = $this->sendOffer($admin, $applicationId);

        $this->actingAs($admin)
            ->postJson("/offers/{$offerId}/send", [], ['Idempotency-Key' => 'of-double-send-'.uniqid('', true)])
            ->assertStatus(409)->assertJsonPath('error.code', 'OFFER_INVALID_TRANSITION');
    }

    public function test_accept_draft_offer_rejected_not_sent(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-accept-draft@example.test');
        [$candidate] = $this->candidate('of-accept-draft-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerId = $this->createOffer($admin, $applicationId);

        $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [])
            ->assertStatus(409)->assertJsonPath('error.code', 'OFFER_NOT_SENT');
    }

    public function test_accept_already_responded_offer_rejected(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-accept-twice@example.test');
        [$candidate] = $this->candidate('of-accept-twice-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerId = $this->sendOffer($admin, $applicationId);

        $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [])->assertOk();
        $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [])
            ->assertStatus(409)->assertJsonPath('error.code', 'OFFER_ALREADY_RESPONDED');
    }

    public function test_accept_after_deadline_rejected(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-deadline@example.test');
        [$candidate] = $this->candidate('of-deadline-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $offerId = (int) $this->actingAs($admin)->postJson("/applications/{$applicationId}/offers", $this->offerPayload([
            'response_deadline' => now()->addDay()->toIso8601String(),
        ]))->assertCreated()->json('data.id');
        $this->actingAs($admin)
            ->postJson("/offers/{$offerId}/send", [], ['Idempotency-Key' => 'of-deadline-send-'.uniqid('', true)])
            ->assertOk();

        Carbon::setTestNow(now()->addDays(2));

        $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [])
            ->assertStatus(409)->assertJsonPath('error.code', 'OFFER_EXPIRED');
    }

    // ---------------------------------------------------------------
    // INV-031
    // ---------------------------------------------------------------

    public function test_inv031_second_offer_creation_denied_after_acceptance(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-inv031@example.test');
        [$candidate] = $this->candidate('of-inv031-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerId = $this->sendOffer($admin, $applicationId);
        $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [])->assertOk();

        // Acceptance already sets applications.current_status = HIRED, one of
        // the four terminal statuses (EV-1/OF-1 shared vocabulary) — the
        // create Action's terminal-application check fires before its
        // separate "already has an ACCEPTED offer" check is ever reached.
        // Both guards independently protect INV-031; this proves the
        // terminal-status guard is the one that actually fires first here.
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/offers", $this->offerPayload())
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_TERMINAL');
    }

    public function test_inv031_accepting_second_offer_denied(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-inv031-second@example.test');
        [$candidate] = $this->candidate('of-inv031-second-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerA = $this->sendOffer($admin, $applicationId);
        $offerB = $this->sendOffer($admin, $applicationId);

        $this->actingAs($candidate)->postJson("/offers/{$offerA}/accept", [])->assertOk();
        $this->actingAs($candidate)->postJson("/offers/{$offerB}/accept", [])
            ->assertStatus(409)->assertJsonPath('error.code', 'OFFER_ALREADY_ACCEPTED_FOR_APPLICATION');

        self::assertSame(1, DB::table('offers')->where('application_id', $applicationId)->where('status', 'ACCEPTED')->count());
    }

    // ---------------------------------------------------------------
    // Notifications
    // ---------------------------------------------------------------

    public function test_notifications_send_accept_reject(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-notify@example.test');
        [$candidate] = $this->candidate('of-notify-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $offerId = (int) $this->actingAs($admin)->postJson("/applications/{$applicationId}/offers", $this->offerPayload())
            ->assertCreated()->json('data.id');
        self::assertSame(0, DB::table('notifications')->where('related_object_type', 'offer')->where('related_object_id', $offerId)->count());

        $this->actingAs($admin)
            ->postJson("/offers/{$offerId}/send", [], ['Idempotency-Key' => 'of-notify-send-'.uniqid('', true)])
            ->assertOk();
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'offer')->where('related_object_id', $offerId)
            ->where('body_reference', 'offer.sent.candidate')->count());

        $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [])->assertOk();
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'offer')->where('related_object_id', $offerId)
            ->where('body_reference', 'offer.accepted.candidate')->count());
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'offer')->where('related_object_id', $offerId)
            ->where('body_reference', 'offer.accepted.owner')->count());

        [$candidate2] = $this->candidate('of-notify-c2@example.test');
        $applicationId2 = $this->submitApplication($candidate2, $vacancyId);
        $offerId2 = $this->sendOffer($admin, $applicationId2);
        $this->actingAs($candidate2)->postJson("/offers/{$offerId2}/reject", [])->assertOk();
        self::assertSame(0, DB::table('notifications')->where('related_object_type', 'offer')->where('related_object_id', $offerId2)
            ->where('body_reference', 'offer.rejected.candidate')->count());
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'offer')->where('related_object_id', $offerId2)
            ->where('body_reference', 'offer.rejected.owner')->count());
        self::assertSame(0, DB::table('email_outbox')->where('template_reference', 'offer.rejected.candidate')->count());
        self::assertSame(1, DB::table('email_outbox')->where('template_reference', 'offer.rejected.owner')
            ->where('related_object_id', $offerId2)->count());
    }

    // ---------------------------------------------------------------
    // Idempotency
    // ---------------------------------------------------------------

    public function test_send_idempotency_replay(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-idem-send@example.test');
        [$candidate] = $this->candidate('of-idem-send-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerId = $this->createOffer($admin, $applicationId);
        $key = 'of-send-'.uniqid('', true);

        $first = $this->actingAs($admin)->postJson("/offers/{$offerId}/send", [], ['Idempotency-Key' => $key])->assertOk();
        $replay = $this->actingAs($admin)->postJson("/offers/{$offerId}/send", [], ['Idempotency-Key' => $key])->assertOk();

        self::assertEquals($first->json('data'), $replay->json('data'));
        self::assertSame(1, DB::table('audit_logs')->where('action', 'offer_sent')->where('object_id', $offerId)->count());
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'offer')->where('related_object_id', $offerId)->count());
    }

    public function test_accept_idempotency_replay(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-idem-accept@example.test');
        [$candidate] = $this->candidate('of-idem-accept-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerId = $this->sendOffer($admin, $applicationId);
        $key = 'of-accept-'.uniqid('', true);

        $first = $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [], ['Idempotency-Key' => $key])->assertOk();
        $replay = $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [], ['Idempotency-Key' => $key])->assertOk();

        self::assertEquals($first->json('data'), $replay->json('data'));
        self::assertSame(1, DB::table('audit_logs')->where('action', 'offer_accepted')->where('object_id', $offerId)->count());
        self::assertSame(1, DB::table('application_status_histories')->where('application_id', $applicationId)->where('event_type', 'OFFER_ACCEPTED')->count());
        self::assertSame('HIRED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
    }

    public function test_reject_idempotency_replay(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-idem-reject@example.test');
        [$candidate] = $this->candidate('of-idem-reject-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerId = $this->sendOffer($admin, $applicationId);
        $key = 'of-reject-'.uniqid('', true);

        $first = $this->actingAs($candidate)->postJson("/offers/{$offerId}/reject", [], ['Idempotency-Key' => $key])->assertOk();
        $replay = $this->actingAs($candidate)->postJson("/offers/{$offerId}/reject", [], ['Idempotency-Key' => $key])->assertOk();

        self::assertEquals($first->json('data'), $replay->json('data'));
        self::assertSame(1, DB::table('audit_logs')->where('action', 'offer_rejected')->where('object_id', $offerId)->count());
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'offer')->where('related_object_id', $offerId)
            ->where('body_reference', 'offer.rejected.owner')->count());
        self::assertSame(0, DB::table('notifications')->where('related_object_type', 'offer')->where('related_object_id', $offerId)
            ->where('body_reference', 'offer.rejected.candidate')->count());
        self::assertSame(0, DB::table('email_outbox')->where('template_reference', 'offer.rejected.candidate')->count());
    }

    // ---------------------------------------------------------------
    // Independence
    // ---------------------------------------------------------------

    public function test_no_evaluation_schedule_stage_or_outcome_side_effects(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('of-independence@example.test');
        [$candidate] = $this->candidate('of-independence-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $offerId = $this->sendOffer($admin, $applicationId);
        $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [])->assertOk();

        self::assertSame(0, DB::table('evaluations')->where('application_id', $applicationId)->count());
        self::assertSame(0, DB::table('selection_schedules')->where('application_id', $applicationId)->count());
        self::assertSame(0, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());
        self::assertNull(DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    /** @return array{0: User, 1: \App\Domains\Company\Models\Company, 2: int} */
    private function openVacancy(string $email, array $overrides = []): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', array_merge([
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ], $overrides));
        $approver = $this->moderator('approver-of-'.$vacancyId.'@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$vacancyId}/approve")->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update([
            'current_status' => 'PUBLISHED', 'published_at' => $open,
        ]);
        Carbon::setTestNow($close->copy()->subDays(10));

        return [$recruiter, $company, $vacancyId];
    }

    private function companyOf(int $vacancyId): int
    {
        return (int) DB::table('vacancies')->where('id', $vacancyId)->value('company_id');
    }

    private function recruiterMember(int $companyId, string $email): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::CompanyRecruiter);
        $this->addMember($companyId, $user, 'COMPANY_RECRUITER');

        return $user->fresh();
    }

    /** @return array{0: User, 1: int} */
    private function candidate(string $email): array
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::CandidateExternal);
        $profileId = (int) DB::table('candidate_profiles')->insertGetId([
            'user_id' => $user->id, 'current_candidate_type' => 'EXTERNAL', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$user, $profileId];
    }

    private function submitApplication(User $candidate, int $vacancyId): int
    {
        return (int) $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/applications", [
            'consent' => [
                'consent_version' => \App\Domains\Application\Support\ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => 'sha256:'.str_repeat('a', 64),
                'accepted' => true,
            ],
        ])->assertCreated()->json('data.id');
    }

    private function createOffer(User $admin, int $applicationId): int
    {
        return (int) $this->actingAs($admin)->postJson("/applications/{$applicationId}/offers", $this->offerPayload())
            ->assertCreated()->json('data.id');
    }

    private function sendOffer(User $admin, int $applicationId, ?int $offerId = null): int
    {
        $offerId ??= $this->createOffer($admin, $applicationId);
        $this->actingAs($admin)
            ->postJson("/offers/{$offerId}/send", [], ['Idempotency-Key' => 'fixture-send-'.uniqid('', true).'-'.random_int(100000, 999999)])
            ->assertOk();

        return $offerId;
    }

    /** @return array<string, mixed> */
    private function offerPayload(array $overrides = []): array
    {
        return array_merge([
            'note' => 'Selamat, Anda kami tawarkan posisi ini.',
        ], $overrides);
    }
}
