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
 * Selection Schedule Foundation v1 — create, reschedule, cancel, list,
 * detail, history. SS-1, SS-2, SS-3, SS-5, SS-8, SS-9 all approved and
 * CLOSED. complete/no-show, selector assignment, evaluation, offer,
 * outcome, reopen, Campus recruitment, External Apply, and the schedule
 * attachment upload mechanism remain deliberately unimplemented.
 */
final class SelectionScheduleTest extends VacancyTestCase
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

        self::assertTrue($registered->contains('POST applications/{application}/schedules'));
        self::assertTrue($registered->contains('GET|HEAD schedules'));
        self::assertTrue($registered->contains('GET|HEAD schedules/{schedule}'));
        self::assertTrue($registered->contains('GET|HEAD schedules/{schedule}/history'));
        self::assertTrue($registered->contains('PATCH schedules/{schedule}'));
        self::assertTrue($registered->contains('POST schedules/{schedule}/cancel'));

        foreach (['complete', 'no-show', 'selector-assignment'] as $absent) {
            self::assertFalse(
                $registered->contains(fn (string $r): bool => str_contains($r, $absent)),
                "No route may exist for {$absent} in this milestone.",
            );
        }
        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'DELETE') && str_contains($r, 'schedule')));
        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'api/v1') && str_contains($r, 'schedule')));
    }

    // ---------------------------------------------------------------
    // Authorization — write
    // ---------------------------------------------------------------

    public function test_recruiter_admin_and_super_admin_can_create(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ss-write-actors@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        $recruiter = $this->recruiterMember($company->id, 'ss-write-actors-r@example.test');
        $superAdmin = $this->moderator('ss-write-actors-sa@example.test', RoleCode::SuperAdmin);

        foreach ([$admin, $recruiter, $superAdmin] as $actor) {
            [$candidate] = $this->candidate('ss-write-actors-c-'.$actor->id.'@example.test');
            $applicationId = $this->submitApplication($candidate, $vacancyId);

            $this->actingAs($actor)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage))
                ->assertCreated()->assertJsonPath('data.status', 'SCHEDULED');
        }
    }

    public function test_revoked_membership_cannot_create(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ss-revoked@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss-revoked-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        DB::table('company_members')->where('company_id', $company->id)->where('user_id', $admin->id)
            ->update(['status' => 'INACTIVE']);

        $this->actingAs($admin->fresh())->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage))
            ->assertNotFound();
    }

    public function test_cross_company_recruiter_gets_enumeration_safe_404(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-cross-a@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [, $companyB] = $this->verifiedCompanyWithRecruiter('ss-cross-b@example.test');
        [$candidate] = $this->candidate('ss-cross-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $recruiterB = $this->recruiterMember($companyB->id, 'ss-cross-recruiter-b@example.test');

        $this->actingAs($recruiterB)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage))
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_career_center_selector_auditor_and_candidate_denied_write(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-deny-write@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss-deny-write-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $careerCenter = $this->moderator('ss-deny-write-cc@example.test', RoleCode::CareerCenterStaff);
        $selector = $this->moderator('ss-deny-write-sel@example.test', RoleCode::Selector);
        $auditor = $this->moderator('ss-deny-write-aud@example.test', RoleCode::Auditor);

        foreach ([$careerCenter, $selector, $auditor, $candidate] as $actor) {
            $this->actingAs($actor)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage))
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }

        self::assertSame(0, DB::table('selection_schedules')->where('application_id', $applicationId)->count());
    }

    // ---------------------------------------------------------------
    // Authorization — read
    // ---------------------------------------------------------------

    public function test_read_authorization_matrix(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ss-read-auth@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss-read-auth-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $scheduleId = $this->createSchedule($admin, $applicationId, $stage);

        $recruiter = $this->recruiterMember($company->id, 'ss-read-auth-r@example.test');
        $superAdmin = $this->moderator('ss-read-auth-sa@example.test', RoleCode::SuperAdmin);
        $auditor = $this->moderator('ss-read-auth-aud@example.test', RoleCode::Auditor);
        $careerCenter = $this->moderator('ss-read-auth-cc@example.test', RoleCode::CareerCenterStaff);

        foreach ([$admin, $recruiter, $superAdmin, $auditor] as $actor) {
            $this->actingAs($actor)->getJson('/schedules')->assertOk();
            $this->actingAs($actor)->getJson("/schedules/{$scheduleId}")->assertOk();
            $this->actingAs($actor)->getJson("/schedules/{$scheduleId}/history")->assertOk();
        }

        $this->actingAs($candidate)->getJson('/schedules')->assertOk();
        $this->actingAs($candidate)->getJson("/schedules/{$scheduleId}")->assertOk();

        $this->actingAs($careerCenter)->getJson('/schedules')->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        $this->actingAs($careerCenter)->getJson("/schedules/{$scheduleId}")->assertStatus(403);

        // Auditor may read but never write.
        $this->actingAs($auditor)->postJson("/schedules/{$scheduleId}/cancel", [])
            ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_cross_company_and_foreign_candidate_reads_are_enumeration_safe(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-read-cross-a@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidateA] = $this->candidate('ss-read-cross-c-a@example.test');
        $applicationId = $this->submitApplication($candidateA, $vacancyId);
        $scheduleId = $this->createSchedule($admin, $applicationId, $stage);

        [, $companyB] = $this->verifiedCompanyWithRecruiter('ss-read-cross-b@example.test');
        $recruiterB = $this->recruiterMember($companyB->id, 'ss-read-cross-recruiter-b@example.test');
        $this->actingAs($recruiterB)->getJson("/schedules/{$scheduleId}")->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        [$candidateB] = $this->candidate('ss-read-cross-c-b@example.test');
        $this->actingAs($candidateB)->getJson("/schedules/{$scheduleId}")->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    // ---------------------------------------------------------------
    // SS-1 — terminal application
    // ---------------------------------------------------------------

    public function test_ss1_create_and_reschedule_denied_for_terminal_application_cancel_allowed(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss1@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);

        foreach (['WITHDRAWN', 'HIRED', 'REJECTED', 'NO_SHOW'] as $status) {
            [$candidate] = $this->candidate('ss1-'.mb_strtolower($status).'@example.test');
            $applicationId = $this->submitApplication($candidate, $vacancyId);
            $scheduleId = $this->createSchedule($admin, $applicationId, $stageA);

            $update = ['current_status' => $status];
            if ($status === 'WITHDRAWN') {
                $update['withdrawn_at'] = now();
            }
            DB::table('applications')->where('id', $applicationId)->update($update);

            $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stageA))
                ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_TERMINAL');

            $this->actingAs($admin)->patchJson("/schedules/{$scheduleId}", ['instructions' => 'Updated'])
                ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_TERMINAL');

            $this->actingAs($admin)->postJson("/schedules/{$scheduleId}/cancel", [])
                ->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        }
    }

    // ---------------------------------------------------------------
    // SS-2 — stage alignment
    // ---------------------------------------------------------------

    public function test_ss2_create_targets_any_active_same_vacancy_stage_regardless_of_current_stage(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss2@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageC = $this->createStage($admin, $vacancyId, 'C', 2);
        [$candidate] = $this->candidate('ss2-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stageA]);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stageC))
            ->assertCreated();

        self::assertSame($stageA, DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
        self::assertNull(DB::table('application_status_histories')->where('application_id', $applicationId)->where('event_type', 'STAGE_CHANGED')->value('id'));
    }

    public function test_wrong_vacancy_and_nonexistent_stage_rejected(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss2-wrong-vacancy@example.test');
        [$otherAdmin, , $otherVacancyId] = $this->openVacancy('ss2-wrong-vacancy-other@example.test');
        $foreignStage = $this->createStage($otherAdmin, $otherVacancyId, 'Foreign', 0);
        [$candidate] = $this->candidate('ss2-wrong-vacancy-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($foreignStage))
            ->assertStatus(422)->assertJsonPath('error.code', 'STAGE_NOT_IN_VACANCY');

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload(999999999))
            ->assertStatus(422)->assertJsonPath('error.code', 'STAGE_NOT_IN_VACANCY');
    }

    // ---------------------------------------------------------------
    // SS-3 — inactive stage
    // ---------------------------------------------------------------

    public function test_ss3_create_rejects_inactive_target_reschedule_rejects_disabled_referenced_stage_cancel_allowed(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss3@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss3-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $scheduleId = $this->createSchedule($admin, $applicationId, $stage);

        $this->actingAs($admin)->patchJson("/vacancies/{$vacancyId}/stages/{$stage}", ['active' => false])->assertOk();

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage))
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->actingAs($admin)->patchJson("/schedules/{$scheduleId}", ['instructions' => 'Updated'])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->actingAs($admin)->postJson("/schedules/{$scheduleId}/cancel", [])
            ->assertOk()->assertJsonPath('data.status', 'CANCELLED');
    }

    // ---------------------------------------------------------------
    // SS-5 — past schedule policy
    // ---------------------------------------------------------------

    public function test_ss5_time_validation(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss5@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss5-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        // Past starts_at.
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage, [
            'starts_at' => now()->subHour()->toIso8601String(),
        ]))->assertStatus(422)->assertJsonPath('error.code', 'SCHEDULE_TIME_INVALID');

        // starts_at == now.
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage, [
            'starts_at' => now()->toIso8601String(),
        ]))->assertStatus(422)->assertJsonPath('error.code', 'SCHEDULE_TIME_INVALID');

        // ends_at <= starts_at.
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage, [
            'starts_at' => now()->addDay()->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(),
        ]))->assertStatus(422)->assertJsonPath('error.code', 'SCHEDULE_TIME_INVALID');

        // future start, null end: allowed.
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage, [
            'starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => null,
        ]))->assertCreated();

        // future start, later end: allowed.
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage, [
            'starts_at' => now()->addDays(2)->toIso8601String(), 'ends_at' => now()->addDays(2)->addHour()->toIso8601String(),
        ]))->assertCreated();

        $scheduleId = $this->createSchedule($admin, $applicationId, $stage);
        $this->actingAs($admin)->patchJson("/schedules/{$scheduleId}", ['starts_at' => now()->subHour()->toIso8601String()])
            ->assertStatus(422)->assertJsonPath('error.code', 'SCHEDULE_TIME_INVALID');
    }

    // ---------------------------------------------------------------
    // SS-8 — RA-2
    // ---------------------------------------------------------------

    public function test_ss8_ra2_create_and_reschedule_gated_cancel_exempt(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ss8@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss8-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $scheduleId = $this->createSchedule($admin, $applicationId, $stage);

        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'SUSPENDED']);
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage))
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
        $this->actingAs($admin)->patchJson("/schedules/{$scheduleId}", ['instructions' => 'x'])
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
        // Cancel remains allowed despite SUSPENDED vacancy.
        $this->actingAs($admin)->postJson("/schedules/{$scheduleId}/cancel", [])->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'PUBLISHED']);

        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'SUSPENDED']);
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage))
            ->assertStatus(403)->assertJsonPath('error.code', 'VACANCY_COMPANY_NOT_VERIFIED');
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'VERIFIED']);
    }

    public function test_ss8_super_admin_does_not_bypass_ra2(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss8-super@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss8-super-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'SUSPENDED']);

        $superAdmin = $this->moderator('ss8-super-admin@example.test', RoleCode::SuperAdmin);
        $this->actingAs($superAdmin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage))
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
    }

    // ---------------------------------------------------------------
    // Create
    // ---------------------------------------------------------------

    public function test_create_success_full_side_effects(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-create@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'Wawancara HR', 0, true, 'Interview Tahap 1');
        $pic = $this->recruiterMember($this->companyOf($vacancyId), 'ss-create-pic@example.test');
        [$candidate] = $this->candidate('ss-create-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $response = $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage, [
            'pic_user_id' => $pic->id,
        ]))->assertCreated();

        $scheduleId = (int) $response->json('data.id');
        self::assertSame('SCHEDULED', $response->json('data.status'));
        self::assertSame(0, $response->json('data.revision_number'));

        self::assertSame(1, DB::table('selection_schedule_histories')->where('selection_schedule_id', $scheduleId)->where('event_type', 'CREATED')->count());
        self::assertSame(1, DB::table('audit_logs')->where('action', 'schedule_created')->where('object_id', $scheduleId)->count());

        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'selection_schedule')->where('related_object_id', $scheduleId)
            ->where('body_reference', 'schedule.created.candidate')->count());
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'selection_schedule')->where('related_object_id', $scheduleId)
            ->where('body_reference', 'schedule.created.pic')->count());
        self::assertSame(1, DB::table('email_outbox')->where('related_object_type', 'selection_schedule')->where('related_object_id', $scheduleId)
            ->where('template_reference', 'schedule.created.candidate')->count());

        self::assertNull(DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
    }

    public function test_create_without_pic_sends_no_pic_notification(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-create-nopic@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss-create-nopic-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $response = $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage))->assertCreated();
        $scheduleId = (int) $response->json('data.id');

        self::assertSame(0, DB::table('notifications')->where('related_object_type', 'selection_schedule')->where('related_object_id', $scheduleId)
            ->where('body_reference', 'schedule.created.pic')->count());
    }

    public function test_create_method_detail_validation(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-method@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss-method-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage, [
            'method' => 'ONLINE', 'meeting_url' => null,
        ]))->assertStatus(422)->assertJsonPath('error.code', 'SCHEDULE_METHOD_DETAIL_REQUIRED');

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stage, [
            'method' => 'ON_SITE', 'location' => null, 'meeting_url' => null,
        ]))->assertStatus(422)->assertJsonPath('error.code', 'SCHEDULE_METHOD_DETAIL_REQUIRED');
    }

    public function test_create_idempotency_replay(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-idem-create@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss-idem-create-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $key = 'ss-create-'.uniqid('', true);
        $payload = $this->schedulePayload($stage);

        $first = $this->actingAs($admin)->withHeaders(['Idempotency-Key' => $key])
            ->postJson("/applications/{$applicationId}/schedules", $payload)->assertCreated();
        $replay = $this->actingAs($admin)->withHeaders(['Idempotency-Key' => $key])
            ->postJson("/applications/{$applicationId}/schedules", $payload)->assertCreated();

        self::assertEquals($first->json('data'), $replay->json('data'));
        self::assertSame(1, DB::table('selection_schedules')->where('application_id', $applicationId)->count());
        self::assertSame(1, DB::table('audit_logs')->where('action', 'schedule_created')->count());
    }

    // ---------------------------------------------------------------
    // Reschedule
    // ---------------------------------------------------------------

    public function test_reschedule_success_full_side_effects(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-reschedule@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss-reschedule-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $scheduleId = $this->createSchedule($admin, $applicationId, $stage);

        $newStart = now()->addDays(5)->toIso8601String();
        $newEnd = now()->addDays(5)->addHour()->toIso8601String();
        $response = $this->actingAs($admin)->withHeaders(['If-Match' => '0'])
            ->patchJson("/schedules/{$scheduleId}", ['starts_at' => $newStart, 'ends_at' => $newEnd, 'reason' => 'Konflik jadwal'])
            ->assertOk();

        self::assertSame('SCHEDULED', $response->json('data.status'));
        self::assertSame(1, $response->json('data.revision_number'));

        $event = DB::table('selection_schedule_histories')->where('selection_schedule_id', $scheduleId)->where('event_type', 'RESCHEDULED')->first();
        self::assertNotNull($event);
        self::assertSame(1, (int) $event->resulting_revision_number);
        self::assertSame('Konflik jadwal', $event->reason);
        self::assertNotNull($event->previous_snapshot);

        self::assertSame(1, DB::table('audit_logs')->where('action', 'schedule_updated')->where('object_id', $scheduleId)->count());
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'selection_schedule')->where('related_object_id', $scheduleId)
            ->where('body_reference', 'schedule.rescheduled.candidate')->count());

        self::assertNull(DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
    }

    public function test_reschedule_stale_version_rejected_with_zero_side_effects(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-stale@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss-stale-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $scheduleId = $this->createSchedule($admin, $applicationId, $stage);

        $historyBefore = DB::table('selection_schedule_histories')->where('selection_schedule_id', $scheduleId)->count();
        $notifBefore = DB::table('notifications')->count();

        $this->actingAs($admin)->withHeaders(['If-Match' => '99'])
            ->patchJson("/schedules/{$scheduleId}", ['starts_at' => now()->addDays(3)->toIso8601String()])
            ->assertStatus(409)->assertJsonPath('error.code', 'STALE_VERSION');

        self::assertSame(0, DB::table('selection_schedules')->where('id', $scheduleId)->value('revision_number'));
        self::assertSame($historyBefore, DB::table('selection_schedule_histories')->where('selection_schedule_id', $scheduleId)->count());
        self::assertSame($notifBefore, DB::table('notifications')->count());
    }

    public function test_reschedule_rejects_completed_or_cancelled_schedule(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-reschedule-terminal@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss-reschedule-terminal-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $scheduleId = $this->createSchedule($admin, $applicationId, $stage);

        $this->actingAs($admin)->postJson("/schedules/{$scheduleId}/cancel", [])->assertOk();

        $this->actingAs($admin)->patchJson("/schedules/{$scheduleId}", ['instructions' => 'x'])
            ->assertStatus(409)->assertJsonPath('error.code', 'SCHEDULE_INVALID_TRANSITION');

        $this->actingAs($admin)->postJson("/schedules/{$scheduleId}/cancel", [])
            ->assertStatus(409)->assertJsonPath('error.code', 'SCHEDULE_INVALID_TRANSITION');
    }

    public function test_reschedule_idempotency_replay(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-idem-reschedule@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss-idem-reschedule-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $scheduleId = $this->createSchedule($admin, $applicationId, $stage);
        $key = 'ss-reschedule-'.uniqid('', true);
        $payload = ['starts_at' => now()->addDays(4)->toIso8601String(), 'ends_at' => now()->addDays(4)->addHour()->toIso8601String()];

        $first = $this->actingAs($admin)->withHeaders(['Idempotency-Key' => $key, 'If-Match' => '0'])
            ->patchJson("/schedules/{$scheduleId}", $payload)->assertOk();
        $replay = $this->actingAs($admin)->withHeaders(['Idempotency-Key' => $key, 'If-Match' => '0'])
            ->patchJson("/schedules/{$scheduleId}", $payload)->assertOk();

        self::assertEquals($first->json('data'), $replay->json('data'));
        self::assertSame(1, DB::table('selection_schedule_histories')->where('selection_schedule_id', $scheduleId)->where('event_type', 'RESCHEDULED')->count());
        self::assertSame(1, DB::table('audit_logs')->where('action', 'schedule_updated')->count());
    }

    // ---------------------------------------------------------------
    // Cancel
    // ---------------------------------------------------------------

    public function test_cancel_success_full_side_effects(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-cancel@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss-cancel-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $scheduleId = $this->createSchedule($admin, $applicationId, $stage);

        $this->actingAs($admin)->postJson("/schedules/{$scheduleId}/cancel", ['reason' => 'Dibatalkan kandidat'])
            ->assertOk()->assertJsonPath('data.status', 'CANCELLED');

        self::assertSame('CANCELLED', DB::table('selection_schedules')->where('id', $scheduleId)->value('status'));
        self::assertSame(1, DB::table('selection_schedule_histories')->where('selection_schedule_id', $scheduleId)->where('event_type', 'CANCELLED')->count());
        self::assertSame(1, DB::table('audit_logs')->where('action', 'schedule_cancelled')->where('object_id', $scheduleId)->count());
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'selection_schedule')->where('related_object_id', $scheduleId)
            ->where('body_reference', 'schedule.cancelled.candidate')->count());

        self::assertNull(DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
    }

    public function test_cancel_idempotency_replay(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-idem-cancel@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ss-idem-cancel-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $scheduleId = $this->createSchedule($admin, $applicationId, $stage);
        $key = 'ss-cancel-'.uniqid('', true);

        $first = $this->actingAs($admin)->withHeaders(['Idempotency-Key' => $key])->postJson("/schedules/{$scheduleId}/cancel", [])->assertOk();
        $replay = $this->actingAs($admin)->withHeaders(['Idempotency-Key' => $key])->postJson("/schedules/{$scheduleId}/cancel", [])->assertOk();

        self::assertEquals($first->json('data'), $replay->json('data'));
        self::assertSame(1, DB::table('selection_schedule_histories')->where('selection_schedule_id', $scheduleId)->where('event_type', 'CANCELLED')->count());
        self::assertSame(1, DB::table('audit_logs')->where('action', 'schedule_cancelled')->count());
    }

    // ---------------------------------------------------------------
    // Candidate read + privacy
    // ---------------------------------------------------------------

    public function test_candidate_payload_excludes_internal_fields(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-candidate-payload@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'Wawancara HR', 0, true, 'Interview Tahap 1');
        [$candidate] = $this->candidate('ss-candidate-payload-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $scheduleId = $this->createSchedule($admin, $applicationId, $stage);
        $this->actingAs($admin)->withHeaders(['If-Match' => '0'])
            ->patchJson("/schedules/{$scheduleId}", ['instructions' => 'Bawa KTP', 'reason' => 'internal note'])
            ->assertOk();

        $detail = $this->actingAs($candidate)->getJson("/schedules/{$scheduleId}")->assertOk();
        self::assertArrayNotHasKey('pic_user_id', $detail->json('data'));
        self::assertArrayNotHasKey('revision_number', $detail->json('data'));
        self::assertArrayNotHasKey('recruitment_stage_id', $detail->json('data'));
        self::assertSame('Interview Tahap 1', $detail->json('data.stage_label'));

        $history = $this->actingAs($candidate)->getJson("/schedules/{$scheduleId}/history")->assertOk();
        foreach ($history->json('data.items') as $event) {
            self::assertArrayNotHasKey('reason', $event);
            self::assertArrayNotHasKey('actor_user_id', $event);
            self::assertArrayNotHasKey('previous_snapshot', $event);
        }
        self::assertContains('RESCHEDULED', collect($history->json('data.items'))->pluck('event_type')->all());
    }

    public function test_candidate_list_and_detail_are_own_scope_only(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-candidate-own@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidateA] = $this->candidate('ss-candidate-own-a@example.test');
        $applicationA = $this->submitApplication($candidateA, $vacancyId);
        $scheduleA = $this->createSchedule($admin, $applicationA, $stage);

        [$candidateB] = $this->candidate('ss-candidate-own-b@example.test');
        $applicationB = $this->submitApplication($candidateB, $vacancyId);
        $this->createSchedule($admin, $applicationB, $stage);

        $list = $this->actingAs($candidateA)->getJson('/schedules')->assertOk();
        self::assertSame([$scheduleA], $list->json('data.items.*.id'));
    }

    // ---------------------------------------------------------------
    // Stage Authoring / Movement boundary
    // ---------------------------------------------------------------

    public function test_stage_reorder_and_rename_never_affect_schedules(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ss-stage-boundary@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'B', 1);
        [$candidate] = $this->candidate('ss-stage-boundary-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $scheduleId = $this->createSchedule($admin, $applicationId, $stageA);

        $this->actingAs($admin)->patchJson("/vacancies/{$vacancyId}/stages/{$stageA}", ['name' => 'Renamed'])->assertOk();
        $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages/reorder", ['stage_ids' => [$stageB, $stageA]])->assertOk();

        self::assertSame($stageA, DB::table('selection_schedules')->where('id', $scheduleId)->value('recruitment_stage_id'));
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
        $approver = $this->moderator('approver-ss-'.$vacancyId.'@example.test');
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

    private function createStage(User $admin, int $vacancyId, string $name, int $sortOrder, bool $active = true, ?string $candidateVisibleLabel = null): int
    {
        return (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => $name, 'stage_type' => 'GENERAL', 'sort_order' => $sortOrder, 'active' => $active,
            'candidate_visible_label' => $candidateVisibleLabel,
        ])->assertCreated()->json('data.id');
    }

    private function createSchedule(User $admin, int $applicationId, int $stageId): int
    {
        return (int) $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", $this->schedulePayload($stageId))
            ->assertCreated()->json('data.id');
    }

    /** @return array<string, mixed> */
    private function schedulePayload(int $stageId, array $overrides = []): array
    {
        return array_merge([
            'recruitment_stage_id' => $stageId,
            'selection_type' => 'INTERVIEW',
            'starts_at' => now()->addDays(3)->toIso8601String(),
            'ends_at' => now()->addDays(3)->addHour()->toIso8601String(),
            'timezone' => 'Asia/Jakarta',
            'method' => 'ONLINE',
            'meeting_url' => 'https://meet.example.test/room',
            'instructions' => 'Siapkan dokumen pendukung.',
        ], $overrides);
    }
}
