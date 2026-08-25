<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Identity\Enums\RoleCode;
use Illuminate\Support\Facades\DB;

/** B-2 restore targets, suspension, and the two close paths of B-3. */
final class VacancySuspendRestoreCloseTest extends VacancyTestCase
{
    public function test_a_published_vacancy_is_suspended_with_a_reason_and_keeps_published_at(): void
    {
        [$recruiter, $company, $id] = $this->published('sus-basic@example.test');
        $publishedAt = DB::table('vacancies')->where('id', $id)->value('published_at');
        $moderator = $this->moderator('sus-basic-cc@example.test');

        $this->actingAs($moderator)->postJson("/vacancies/{$id}/suspend", [])
            ->assertStatus(422)->assertJsonPath('error.code', 'REVIEW_REASON_REQUIRED');

        $this->actingAs($moderator)->postJson("/vacancies/{$id}/suspend", [
            'reason_category' => 'COMPLAINT', 'recruiter_visible_note' => 'Ditangguhkan sementara.',
        ])->assertOk()->assertJsonPath('data.current_status', 'SUSPENDED');

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('SUSPENDED', $row->current_status);
        self::assertSame($publishedAt, $row->published_at, 'Suspension never rewrites published_at.');
        self::assertNotNull($row->suspended_at);
        self::assertSame(1, $this->auditCount('vacancy_suspended', $id));
        self::assertSame(['APPROVE', 'SUSPEND'], array_column($this->reviewRows($id), 'action'));
    }

    public function test_restore_before_close_at_returns_to_published_and_preserves_published_at(): void
    {
        [$recruiter, $company, $id] = $this->published('res-early@example.test');
        $publishedAt = DB::table('vacancies')->where('id', $id)->value('published_at');
        $moderator = $this->moderator('res-early-cc@example.test');
        $this->suspend($moderator, $id);

        $this->travel(1)->hours();
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/restore")
            ->assertOk()->assertJsonPath('data.current_status', 'PUBLISHED');

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('PUBLISHED', $row->current_status);
        self::assertSame($publishedAt, $row->published_at, 'B-2: the original published_at survives.');
        self::assertNull($row->suspended_at);
        self::assertNull($row->closed_at);
        self::assertSame(1, $this->auditCount('vacancy_restored', $id));
        self::assertSame(1, $this->auditCount('vacancy_published', $id), 'Restore is not a republication.');
    }

    public function test_restore_at_or_after_close_at_closes_and_stamps_closed_at(): void
    {
        [$recruiter, $company, $id] = $this->published('res-late@example.test', now()->addDay());
        $publishedAt = DB::table('vacancies')->where('id', $id)->value('published_at');
        $moderator = $this->moderator('res-late-cc@example.test');
        $this->suspend($moderator, $id);

        // Past close_at by the time the restore is executed.
        $this->travel(2)->days();
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/restore")
            ->assertOk()->assertJsonPath('data.current_status', 'CLOSED');

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('CLOSED', $row->current_status);
        self::assertNotNull($row->closed_at);
        self::assertSame($publishedAt, $row->published_at);
        self::assertNull($row->suspended_at);
        // B-2: restore emits vacancy_restored only; no second close event.
        self::assertSame(1, $this->auditCount('vacancy_restored', $id));
        self::assertSame(0, $this->auditCount('vacancy_closed', $id));
        $restore = collect($this->reviewRows($id))->firstWhere('action', 'RESTORE');
        self::assertNotNull($restore);
        self::assertSame('CLOSED', $restore['to_status']);
    }

    public function test_restore_never_returns_approved_or_scheduled(): void
    {
        [$recruiter, $company, $id] = $this->published('res-target@example.test');
        $moderator = $this->moderator('res-target-cc@example.test');
        $this->suspend($moderator, $id);

        $this->actingAs($moderator)->postJson("/vacancies/{$id}/restore")->assertOk();

        $status = DB::table('vacancies')->where('id', $id)->value('current_status');
        self::assertContains($status, ['PUBLISHED', 'CLOSED']);
        self::assertSame(0, DB::table('vacancy_moderation_reviews')->where('vacancy_id', $id)
            ->whereIn('to_status', ['APPROVED', 'SCHEDULED'])->count());
    }

    public function test_the_owner_closes_their_own_published_vacancy(): void
    {
        [$recruiter, $company, $id] = $this->published('close-owner@example.test');

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/close", ['note' => 'Posisi sudah terisi.'])
            ->assertOk()->assertJsonPath('data.current_status', 'CLOSED');

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('CLOSED', $row->current_status);
        self::assertNotNull($row->closed_at);
        self::assertSame(1, $this->auditCount('vacancy_closed', $id));
        $close = collect($this->reviewRows($id))->firstWhere('action', 'CLOSE');
        self::assertNotNull($close);
        self::assertSame($recruiter->id, (int) $close['reviewer_user_id'], 'The owner is the reviewer on an owner close.');
    }

    public function test_career_center_and_super_admin_may_also_close(): void
    {
        foreach ([RoleCode::CareerCenterStaff, RoleCode::SuperAdmin] as $index => $role) {
            [$recruiter, $company, $id] = $this->published("close-mod-{$index}@example.test");
            $moderator = $this->moderator("close-mod-actor-{$index}@example.test", $role);

            $this->actingAs($moderator)->postJson("/vacancies/{$id}/close")
                ->assertOk()->assertJsonPath('data.current_status', 'CLOSED');
            self::assertSame(1, $this->auditCount('vacancy_closed', $id));
        }
    }

    public function test_closing_leaves_existing_applications_untouched(): void
    {
        [$recruiter, $company, $id] = $this->published('close-apps@example.test');
        $candidate = $this->makeUser('close-apps-candidate@example.test');
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidate->id, 'current_candidate_type' => 'EXTERNAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('applications')->insert([
            'application_code' => 'APP-CLOSE-'.$id, 'candidate_profile_id' => $profileId, 'vacancy_id' => $id,
            'current_status' => 'APPLIED', 'first_applied_at' => now(), 'reopen_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $before = (array) DB::table('applications')->where('vacancy_id', $id)->first();

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/close")->assertOk();

        self::assertSame($before, (array) DB::table('applications')->where('vacancy_id', $id)->first());
    }

    public function test_illegal_source_states_are_refused_without_side_effects(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('lifecycle-illegal@example.test');
        $moderator = $this->moderator('lifecycle-illegal-cc@example.test');

        $cases = [
            ['DRAFT', 'suspend', ['reason_category' => 'X', 'recruiter_visible_note' => 'Y']],
            ['DRAFT', 'close', []],
            ['PENDING_REVIEW', 'restore', []],
            ['PUBLISHED', 'approve', []],
            ['CLOSED', 'suspend', ['reason_category' => 'X', 'recruiter_visible_note' => 'Y']],
            ['REJECTED', 'request-revision', ['reason_category' => 'X', 'recruiter_visible_note' => 'Y']],
        ];

        foreach ($cases as [$status, $action, $payload]) {
            $id = $this->vacancyAt($recruiter, $company, $status);
            $this->actingAs($moderator)->postJson("/vacancies/{$id}/{$action}", $payload)
                ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');

            self::assertSame($status, DB::table('vacancies')->where('id', $id)->value('current_status'));
            self::assertSame([], $this->reviewRows($id));
            self::assertSame(0, $this->outboxCount($id));
        }
    }

    /** @return array{\App\Domains\Identity\Models\User, \App\Domains\Company\Models\Company, int} */
    private function published(string $email, $closeAt = null): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => now()->subHour()->toIso8601String(),
            'close_at' => ($closeAt ?? now()->addDays(30))->toIso8601String(),
        ]);
        $approver = $this->moderator('approver-'.$email);
        $this->actingAs($approver)->postJson("/vacancies/{$id}/approve")
            ->assertOk()->assertJsonPath('data.current_status', 'PUBLISHED');

        return [$recruiter, $company, $id];
    }

    private function suspend($moderator, int $id): void
    {
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/suspend", [
            'reason_category' => 'COMPLAINT', 'recruiter_visible_note' => 'Ditangguhkan.',
        ])->assertOk();
    }
}
