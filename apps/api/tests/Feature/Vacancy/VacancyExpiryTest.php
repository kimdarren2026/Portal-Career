<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Vacancy\Actions\ExpirePublishedVacancies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * O-7 — automatic expiry. `close_at` is an exclusive end boundary, only
 * PUBLISHED company vacancies expire, and expiry writes the status and nothing
 * else.
 */
final class VacancyExpiryTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_published_vacancy_before_close_at_is_still_active(): void
    {
        [$recruiter, $company, $id] = $this->published('expiry-active@example.test');

        self::assertSame(0, app(ExpirePublishedVacancies::class)->execute());

        self::assertSame('PUBLISHED', DB::table('vacancies')->where('id', $id)->value('current_status'));
        self::assertSame(0, $this->auditCount('vacancy_expired', $id));
    }

    public function test_expiry_eligibility_is_reached_exactly_at_close_at(): void
    {
        $close = Carbon::parse('2026-10-01T09:00:00+00:00');
        [$recruiter, $company, $id] = $this->published('expiry-boundary@example.test', $close);
        $publishedAt = DB::table('vacancies')->where('id', $id)->value('published_at');

        // One second before: still active. close_at is an exclusive end.
        Carbon::setTestNow($close->copy()->subSecond());
        self::assertSame(0, app(ExpirePublishedVacancies::class)->execute());
        self::assertSame('PUBLISHED', DB::table('vacancies')->where('id', $id)->value('current_status'));

        // Exactly at close_at: eligibility is reached.
        Carbon::setTestNow($close);
        self::assertSame(1, app(ExpirePublishedVacancies::class)->execute());

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('EXPIRED', $row->current_status);
        self::assertSame($publishedAt, $row->published_at, 'published_at is preserved (INV-013).');
        self::assertNull($row->closed_at, 'Expiry is not a close: closed_at is never written.');
        self::assertNull($row->suspended_at);
    }

    public function test_expiry_after_close_at_writes_only_the_status_and_the_audit(): void
    {
        $close = Carbon::parse('2026-10-01T09:00:00+00:00');
        [$recruiter, $company, $id] = $this->published('expiry-after@example.test', $close);
        $versionsBefore = DB::table('vacancy_versions')->where('vacancy_id', $id)->count();
        $reviewsBefore = $this->reviewRows($id);
        $outboxBefore = $this->outboxCount($id);
        $notificationsBefore = DB::table('notifications')->where('related_object_id', $id)->count();

        Carbon::setTestNow($close->copy()->addDays(3));
        self::assertSame(1, app(ExpirePublishedVacancies::class)->execute());

        self::assertSame('EXPIRED', DB::table('vacancies')->where('id', $id)->value('current_status'));
        self::assertSame(1, $this->auditCount('vacancy_expired', $id));

        // No moderation review, no version snapshot, and no notification of any
        // kind: FSD v1.1 defines no expiry notification trigger.
        self::assertSame($reviewsBefore, $this->reviewRows($id));
        self::assertSame($versionsBefore, DB::table('vacancy_versions')->where('vacancy_id', $id)->count());
        self::assertSame($outboxBefore, $this->outboxCount($id));
        self::assertSame($notificationsBefore, DB::table('notifications')->where('related_object_id', $id)->count());
    }

    public function test_the_expiry_audit_is_written_by_the_system_with_no_actor(): void
    {
        $close = Carbon::parse('2026-10-01T09:00:00+00:00');
        [$recruiter, $company, $id] = $this->published('expiry-actor@example.test', $close);

        Carbon::setTestNow($close);
        app(ExpirePublishedVacancies::class)->execute();

        $audit = DB::table('audit_logs')->where('action', 'vacancy_expired')->where('object_id', $id)->first();
        self::assertNotNull($audit);
        self::assertNull($audit->actor_user_id, 'System expiry invents no user identity.');
        self::assertSame('vacancy', $audit->object_type);
        $summary = json_decode((string) $audit->change_summary, true);
        self::assertSame('PUBLISHED', $summary['from_status']);
        self::assertSame('EXPIRED', $summary['to_status']);
    }

    public function test_only_published_company_vacancies_expire(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('expiry-scope@example.test');
        $close = Carbon::parse('2026-10-01T09:00:00+00:00');
        $ids = [];
        foreach (['DRAFT', 'PENDING_REVIEW', 'REVISION_REQUIRED', 'SCHEDULED', 'APPROVED', 'REJECTED', 'SUSPENDED', 'CLOSED', 'EXPIRED'] as $status) {
            $id = $this->vacancyAt($recruiter, $company, $status, [
                'open_at' => $close->copy()->subDays(20)->toIso8601String(),
                'close_at' => $close->toIso8601String(),
            ]);
            $ids[$status] = $id;
        }

        Carbon::setTestNow($close->copy()->addDay());
        self::assertSame(0, app(ExpirePublishedVacancies::class)->execute());

        foreach ($ids as $status => $id) {
            self::assertSame($status, DB::table('vacancies')->where('id', $id)->value('current_status'), $status.' must be untouched.');
            self::assertSame(0, $this->auditCount('vacancy_expired', $id));
        }
    }

    public function test_a_suspended_vacancy_past_close_at_is_left_to_b2_restore(): void
    {
        $close = Carbon::parse('2026-10-01T09:00:00+00:00');
        [$recruiter, $company, $id] = $this->published('expiry-suspended@example.test', $close);
        $moderator = $this->moderator('expiry-suspended-cc@example.test');
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/suspend", [
            'reason_category' => 'COMPLAINT', 'recruiter_visible_note' => 'Ditangguhkan.',
        ])->assertOk();

        Carbon::setTestNow($close->copy()->addDay());
        self::assertSame(0, app(ExpirePublishedVacancies::class)->execute());
        self::assertSame('SUSPENDED', DB::table('vacancies')->where('id', $id)->value('current_status'));

        // B-2 remains authoritative: an explicit restore past close_at closes it.
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/restore")
            ->assertOk()->assertJsonPath('data.current_status', 'CLOSED');
        self::assertSame(0, $this->auditCount('vacancy_expired', $id));
    }

    public function test_a_repeated_run_neither_re_expires_nor_duplicates_the_audit(): void
    {
        $close = Carbon::parse('2026-10-01T09:00:00+00:00');
        [$recruiter, $company, $id] = $this->published('expiry-repeat@example.test', $close);

        Carbon::setTestNow($close);
        self::assertSame(1, app(ExpirePublishedVacancies::class)->execute());
        $updatedAt = DB::table('vacancies')->where('id', $id)->value('updated_at');

        Carbon::setTestNow($close->copy()->addDays(7));
        self::assertSame(0, app(ExpirePublishedVacancies::class)->execute());
        $this->artisan('vacancies:expire')->assertSuccessful();

        self::assertSame('EXPIRED', DB::table('vacancies')->where('id', $id)->value('current_status'));
        self::assertSame($updatedAt, DB::table('vacancies')->where('id', $id)->value('updated_at'));
        self::assertSame(1, $this->auditCount('vacancy_expired', $id), 'Idempotent: one audit entry only.');
    }

    public function test_expired_is_terminal_and_no_actor_can_move_it(): void
    {
        $close = Carbon::parse('2026-10-01T09:00:00+00:00');
        [$recruiter, $company, $id] = $this->published('expiry-terminal@example.test', $close);
        Carbon::setTestNow($close);
        app(ExpirePublishedVacancies::class)->execute();

        $moderator = $this->moderator('expiry-terminal-cc@example.test');
        $superAdmin = $this->moderator('expiry-terminal-sa@example.test', RoleCode::SuperAdmin);

        // No restore, reopen or un-expire transition exists for anyone.
        foreach ([$moderator, $superAdmin] as $actor) {
            foreach (['restore', 'approve', 'suspend', 'close', 'request-revision', 'reject'] as $action) {
                $this->actingAs($actor)->postJson("/vacancies/{$id}/{$action}", [
                    'reason_category' => 'X', 'recruiter_visible_note' => 'Y',
                ])->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');
            }
        }
        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/close")
            ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');
        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")
            ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');

        self::assertSame('EXPIRED', DB::table('vacancies')->where('id', $id)->value('current_status'));
    }

    public function test_expiry_leaves_existing_applications_untouched(): void
    {
        $close = Carbon::parse('2026-10-01T09:00:00+00:00');
        [$recruiter, $company, $id] = $this->published('expiry-apps@example.test', $close);
        $candidate = $this->makeUser('expiry-apps-candidate@example.test');
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidate->id, 'current_candidate_type' => 'EXTERNAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('applications')->insert([
            'application_code' => 'APP-EXP-'.$id, 'candidate_profile_id' => $profileId, 'vacancy_id' => $id,
            'current_status' => 'APPLIED', 'first_applied_at' => now(), 'reopen_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $before = (array) DB::table('applications')->where('vacancy_id', $id)->first();

        Carbon::setTestNow($close->copy()->addDay());
        app(ExpirePublishedVacancies::class)->execute();

        self::assertSame($before, (array) DB::table('applications')->where('vacancy_id', $id)->first());
    }

    public function test_a_campus_vacancy_past_close_date_is_expired_by_the_scheduler(): void
    {
        // CAMPUS_SCOPE activation (approved PO / SPEC-DOC): FSD §8.4
        // "PUBLISHED | Pass close date | EXPIRED" applies to campus too. Same
        // semantics as company: status + audit only, no notification.
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('expiry-campus@example.test');
        $close = Carbon::parse('2026-10-01T09:00:00+00:00');
        $unitId = DB::table('organizational_units')->insertGetId([
            'code' => 'UNIT-EXP', 'name' => 'Unit Kepegawaian', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $campusId = DB::table('vacancies')->insertGetId([
            'vacancy_code' => 'VAC-CAMPUS-EXP', 'slug' => 'campus-expiry-fixture',
            'vacancy_type' => 'CAMPUS_EMPLOYMENT', 'ownership_type' => 'CAMPUS',
            'company_id' => null, 'organizational_unit_id' => $unitId,
            'title' => 'Staf Kampus', 'description' => 'Deskripsi.',
            'employment_type' => 'FULL_TIME', 'openings_count' => 1,
            'target_audience' => 'INTERNAL', 'application_method' => 'IN_PORTAL',
            'current_status' => 'PUBLISHED', 'created_by' => $recruiter->id,
            'open_at' => $close->copy()->subDays(20), 'close_at' => $close,
            'published_at' => $close->copy()->subDays(20),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Carbon::setTestNow($close->copy()->addDay());
        self::assertSame(1, app(ExpirePublishedVacancies::class)->execute());

        self::assertSame('EXPIRED', DB::table('vacancies')->where('id', $campusId)->value('current_status'));
        self::assertSame(1, $this->auditCount('vacancy_expired', $campusId));
    }

    public function test_application_data_survives_both_terminal_routes(): void
    {
        $close = Carbon::parse('2026-10-01T09:00:00+00:00');

        // Route one: manual close.
        [$recruiterA, , $closedId] = $this->published('expiry-terminal-close@example.test', $close);
        $beforeClose = $this->applicationFor($closedId, 'expiry-terminal-close-cand@example.test');
        $this->actingAs($recruiterA)->postJson("/vacancies/{$closedId}/close")->assertOk();
        self::assertSame($beforeClose, (array) DB::table('applications')->where('vacancy_id', $closedId)->first());
        self::assertSame('CLOSED', DB::table('vacancies')->where('id', $closedId)->value('current_status'));

        // Route two: automatic expiry.
        [, , $expiredId] = $this->published('expiry-terminal-expire@example.test', $close);
        $beforeExpiry = $this->applicationFor($expiredId, 'expiry-terminal-expire-cand@example.test');
        Carbon::setTestNow($close->copy()->addDay());
        app(ExpirePublishedVacancies::class)->execute();
        self::assertSame($beforeExpiry, (array) DB::table('applications')->where('vacancy_id', $expiredId)->first());
        self::assertSame('EXPIRED', DB::table('vacancies')->where('id', $expiredId)->value('current_status'));
    }

    public function test_expiry_has_no_route_and_no_user_capability(): void
    {
        $registered = collect(Route::getRoutes())->map(static fn ($route): string => $route->uri());
        self::assertFalse($registered->contains(fn (string $uri): bool => str_contains($uri, 'expire')));
        self::assertNull(Route::getRoutes()->getByName('vacancies.expire'));
    }

    /** @return array<string, mixed> The application row as stored. */
    private function applicationFor(int $vacancyId, string $email): array
    {
        $candidate = $this->makeUser($email);
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidate->id, 'current_candidate_type' => 'EXTERNAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->sequence++;
        DB::table('applications')->insert([
            'application_code' => 'APP-TERM-'.$this->sequence.'-'.$vacancyId,
            'candidate_profile_id' => $profileId, 'vacancy_id' => $vacancyId,
            'current_status' => 'APPLIED', 'first_applied_at' => now(), 'reopen_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (array) DB::table('applications')->where('vacancy_id', $vacancyId)->first();
    }

    /** @return array{\App\Domains\Identity\Models\User, \App\Domains\Company\Models\Company, int} */
    private function published(string $email, ?Carbon $closeAt = null): array
    {
        $close = $closeAt ?? Carbon::parse('2026-10-01T09:00:00+00:00');
        Carbon::setTestNow($close->copy()->subDays(10));

        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => $close->copy()->subDays(20)->toIso8601String(),
            'close_at' => $close->toIso8601String(),
        ]);
        $approver = $this->moderator('expiry-approver-'.$email);
        $this->actingAs($approver)->postJson("/vacancies/{$id}/approve")
            ->assertOk()->assertJsonPath('data.current_status', 'PUBLISHED');

        return [$recruiter, $company, $id];
    }
}
