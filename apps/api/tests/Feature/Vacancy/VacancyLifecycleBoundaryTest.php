<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The exact time boundaries of B-1 and B-2, driven with frozen time so the
 * equality cases are decided rather than approached.
 *
 * B-1: `now == open_at` publishes; `now == close_at` refuses approval.
 * B-2: `now == close_at` restores to CLOSED.
 */
final class VacancyLifecycleBoundaryTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_approval_exactly_at_open_at_publishes_and_stamps_published_at_with_that_instant(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('boundary-open@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        $moderator = $this->moderator('boundary-open-cc@example.test');

        $instant = Carbon::parse('2026-09-01T08:00:00+00:00');
        DB::table('vacancies')->where('id', $id)->update([
            'open_at' => $instant, 'close_at' => $instant->copy()->addDays(30),
        ]);

        // now === open_at exactly.
        Carbon::setTestNow($instant);
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/approve")
            ->assertOk()->assertJsonPath('data.current_status', 'PUBLISHED');

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('PUBLISHED', $row->current_status);
        self::assertTrue(
            $instant->equalTo(Carbon::parse($row->published_at)),
            'published_at is the frozen instant, not an approximation.',
        );
        self::assertSame(1, $this->auditCount('vacancy_approved', $id));
        self::assertSame(1, $this->auditCount('vacancy_published', $id));
    }

    public function test_approval_exactly_at_close_at_is_refused_and_writes_nothing(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('boundary-close@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        $moderator = $this->moderator('boundary-close-cc@example.test');

        $instant = Carbon::parse('2026-09-01T08:00:00+00:00');
        DB::table('vacancies')->where('id', $id)->update([
            'open_at' => $instant->copy()->subDays(10), 'close_at' => $instant,
        ]);

        // now === close_at exactly: the window has ended.
        Carbon::setTestNow($instant);
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/approve")
            ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('PENDING_REVIEW', $row->current_status);
        self::assertNull($row->published_at);
        self::assertSame([], $this->reviewRows($id));
        self::assertSame(0, $this->auditCount('vacancy_approved', $id));
        self::assertSame(0, $this->auditCount('vacancy_published', $id));
        self::assertSame(0, $this->outboxCount($id));
    }

    public function test_approval_one_second_before_close_at_still_publishes(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('boundary-nearly@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        $moderator = $this->moderator('boundary-nearly-cc@example.test');

        $close = Carbon::parse('2026-09-01T08:00:00+00:00');
        DB::table('vacancies')->where('id', $id)->update([
            'open_at' => $close->copy()->subDays(10), 'close_at' => $close,
        ]);

        // The boundary is exclusive on close_at, so one second earlier is inside.
        Carbon::setTestNow($close->copy()->subSecond());
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/approve")
            ->assertOk()->assertJsonPath('data.current_status', 'PUBLISHED');
    }

    public function test_restore_exactly_at_close_at_closes_and_preserves_published_at(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('boundary-restore@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        $moderator = $this->moderator('boundary-restore-cc@example.test');

        $close = Carbon::parse('2026-09-01T08:00:00+00:00');
        DB::table('vacancies')->where('id', $id)->update([
            'open_at' => $close->copy()->subDays(10), 'close_at' => $close,
        ]);

        Carbon::setTestNow($close->copy()->subDays(5));
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/approve")
            ->assertOk()->assertJsonPath('data.current_status', 'PUBLISHED');
        $publishedAt = DB::table('vacancies')->where('id', $id)->value('published_at');

        $this->actingAs($moderator)->postJson("/vacancies/{$id}/suspend", [
            'reason_category' => 'COMPLAINT', 'recruiter_visible_note' => 'Ditangguhkan.',
        ])->assertOk();

        // now === close_at exactly at the moment of restore.
        Carbon::setTestNow($close);
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/restore")
            ->assertOk()->assertJsonPath('data.current_status', 'CLOSED');

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('CLOSED', $row->current_status);
        self::assertTrue($close->equalTo(Carbon::parse($row->closed_at)), 'closed_at is the restore instant.');
        self::assertSame($publishedAt, $row->published_at, 'B-2 preserves the original published_at.');
        self::assertNull($row->suspended_at, 'Leaving SUSPENDED clears the marker.');
        self::assertSame(1, $this->auditCount('vacancy_restored', $id));
        self::assertSame(0, $this->auditCount('vacancy_closed', $id), 'Restore emits no close event.');
    }

    public function test_restore_one_second_before_close_at_returns_to_published(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('boundary-restore-early@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        $moderator = $this->moderator('boundary-restore-early-cc@example.test');

        $close = Carbon::parse('2026-09-01T08:00:00+00:00');
        DB::table('vacancies')->where('id', $id)->update([
            'open_at' => $close->copy()->subDays(10), 'close_at' => $close,
        ]);

        Carbon::setTestNow($close->copy()->subDays(5));
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/approve")->assertOk();
        $publishedAt = DB::table('vacancies')->where('id', $id)->value('published_at');
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/suspend", [
            'reason_category' => 'COMPLAINT', 'recruiter_visible_note' => 'Ditangguhkan.',
        ])->assertOk();

        Carbon::setTestNow($close->copy()->subSecond());
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/restore")
            ->assertOk()->assertJsonPath('data.current_status', 'PUBLISHED');

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame($publishedAt, $row->published_at);
        self::assertNull($row->closed_at);
        self::assertNull($row->suspended_at);
    }
}
