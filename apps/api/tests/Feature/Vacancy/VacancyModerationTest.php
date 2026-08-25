<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Identity\Enums\RoleCode;
use Illuminate\Support\Facades\DB;

/**
 * Company vacancy moderation — B-1 (approve target), B-3 (authority and
 * conflict of interest) and INV-029 (mandatory reason).
 */
final class VacancyModerationTest extends VacancyTestCase
{
    public function test_career_center_requests_revision_with_a_mandatory_reason(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('mod-revision@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        $moderator = $this->moderator('mod-revision-cc@example.test');

        $this->actingAs($moderator)->postJson("/vacancies/{$id}/request-revision", [
            'reason_category' => 'INCOMPLETE_CONTENT',
            'recruiter_visible_note' => 'Mohon lengkapi deskripsi tugas.',
            'internal_note' => 'Perusahaan ini sering mengirim draft kosong.',
        ])->assertOk()->assertJsonPath('data.current_status', 'REVISION_REQUIRED');

        $review = $this->reviewRows($id)[0];
        self::assertSame('REQUEST_REVISION', $review['action']);
        self::assertSame('PENDING_REVIEW', $review['from_status']);
        self::assertSame('REVISION_REQUIRED', $review['to_status']);
        self::assertSame($moderator->id, (int) $review['reviewer_user_id']);
        self::assertSame(1, $this->auditCount('vacancy_revision_requested', $id));
    }

    public function test_request_revision_requires_both_reason_category_and_recruiter_visible_note(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('mod-reason@example.test');
        $moderator = $this->moderator('mod-reason-cc@example.test');

        foreach ([[], ['reason_category' => 'X'], ['recruiter_visible_note' => 'Y']] as $payload) {
            $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
            $this->actingAs($moderator)->postJson("/vacancies/{$id}/request-revision", $payload)
                ->assertStatus(422)->assertJsonPath('error.code', 'REVIEW_REASON_REQUIRED');

            self::assertSame('PENDING_REVIEW', DB::table('vacancies')->where('id', $id)->value('current_status'));
            self::assertSame([], $this->reviewRows($id));
            self::assertSame(0, $this->auditCount('vacancy_revision_requested', $id));
        }
    }

    public function test_the_internal_note_never_reaches_the_recruiter(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('mod-internal@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        $moderator = $this->moderator('mod-internal-cc@example.test');
        $secret = 'INTERNAL-ONLY-e2f0a1';

        $moderatorResponse = $this->actingAs($moderator)->postJson("/vacancies/{$id}/request-revision", [
            'reason_category' => 'INCOMPLETE_CONTENT',
            'recruiter_visible_note' => 'Lengkapi deskripsi.',
            'internal_note' => $secret,
        ])->assertOk();

        // Stored for authorized readers, absent from every response body...
        self::assertSame($secret, $this->reviewRows($id)[0]['internal_note']);
        self::assertStringNotContainsString($secret, $moderatorResponse->getContent());
        self::assertStringNotContainsString(
            $secret,
            $this->actingAs($recruiter)->getJson("/vacancies/{$id}?include=requirements,screening_questions,versions")
                ->assertOk()->getContent(),
        );
        self::assertStringNotContainsString(
            $secret,
            $this->actingAs($recruiter)->getJson("/vacancies/{$id}/versions")->assertOk()->getContent(),
        );

        // ...and never queued into a notification payload.
        $payloads = DB::table('email_outbox')->where('related_object_id', $id)->pluck('payload_reference')->implode(' ');
        self::assertStringNotContainsString($secret, $payloads);
        self::assertStringContainsString('Lengkapi deskripsi.', $payloads);
    }

    public function test_career_center_rejects_with_a_mandatory_reason(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('mod-reject@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        $moderator = $this->moderator('mod-reject-cc@example.test', RoleCode::CareerCenterManager);

        $this->actingAs($moderator)->postJson("/vacancies/{$id}/reject", [])
            ->assertStatus(422)->assertJsonPath('error.code', 'REVIEW_REASON_REQUIRED');

        $this->actingAs($moderator)->postJson("/vacancies/{$id}/reject", [
            'reason_category' => 'POLICY_VIOLATION',
            'recruiter_visible_note' => 'Lowongan tidak sesuai kebijakan.',
        ])->assertOk()->assertJsonPath('data.current_status', 'REJECTED');

        self::assertSame('REJECT', $this->reviewRows($id)[0]['action']);
        self::assertSame(1, $this->auditCount('vacancy_rejected', $id));
    }

    public function test_approve_before_open_at_schedules_and_leaves_published_at_null(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('mod-schedule@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => now()->addDays(3)->toIso8601String(),
            'close_at' => now()->addDays(30)->toIso8601String(),
        ]);
        $moderator = $this->moderator('mod-schedule-cc@example.test');

        $this->actingAs($moderator)->postJson("/vacancies/{$id}/approve")
            ->assertOk()->assertJsonPath('data.current_status', 'SCHEDULED')
            ->assertJsonPath('data.published_at', null);

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('SCHEDULED', $row->current_status);
        self::assertNull($row->published_at);
        self::assertSame(1, $this->auditCount('vacancy_approved', $id));
        self::assertSame(0, $this->auditCount('vacancy_published', $id), 'Scheduling publishes nothing yet.');
    }

    public function test_approve_inside_the_window_publishes_immediately_and_sets_published_at_once(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('mod-publish@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => now()->subDay()->toIso8601String(),
            'close_at' => now()->addDays(30)->toIso8601String(),
        ]);
        $moderator = $this->moderator('mod-publish-cc@example.test');

        $this->actingAs($moderator)->postJson("/vacancies/{$id}/approve")
            ->assertOk()->assertJsonPath('data.current_status', 'PUBLISHED');

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('PUBLISHED', $row->current_status);
        self::assertNotNull($row->published_at);
        // The approve contract requires both events on immediate publication.
        self::assertSame(1, $this->auditCount('vacancy_approved', $id));
        self::assertSame(1, $this->auditCount('vacancy_published', $id));
        self::assertSame('APPROVE', $this->reviewRows($id)[0]['action']);
        self::assertSame('PUBLISHED', $this->reviewRows($id)[0]['to_status']);
    }

    public function test_approval_at_or_after_close_at_never_succeeds(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('mod-late@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => now()->subDays(30)->toIso8601String(),
            'close_at' => now()->subDay()->toIso8601String(),
        ]);
        $moderator = $this->moderator('mod-late-cc@example.test');

        $this->actingAs($moderator)->postJson("/vacancies/{$id}/approve")
            ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('PENDING_REVIEW', $row->current_status, 'The vacancy stays PENDING_REVIEW.');
        self::assertNull($row->published_at);
        self::assertSame([], $this->reviewRows($id));
        self::assertSame(0, $this->auditCount('vacancy_approved', $id));
    }

    public function test_approval_never_emits_the_approved_status(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('mod-noapproved@example.test');
        $moderator = $this->moderator('mod-noapproved-cc@example.test');

        $future = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => now()->addDay()->toIso8601String(), 'close_at' => now()->addDays(9)->toIso8601String(),
        ]);
        $active = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => now()->subHour()->toIso8601String(), 'close_at' => now()->addDays(9)->toIso8601String(),
        ]);
        $this->actingAs($moderator)->postJson("/vacancies/{$future}/approve")->assertOk();
        $this->actingAs($moderator)->postJson("/vacancies/{$active}/approve")->assertOk();

        self::assertSame(0, DB::table('vacancies')->where('current_status', 'APPROVED')->count());
        self::assertSame(0, DB::table('vacancy_moderation_reviews')->where('to_status', 'APPROVED')->count());
    }

    public function test_super_admin_moderates_under_the_same_rules(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('mod-sa@example.test');
        $superAdmin = $this->moderator('mod-sa-admin@example.test', RoleCode::SuperAdmin);

        $revision = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        $this->actingAs($superAdmin)->postJson("/vacancies/{$revision}/request-revision", [
            'reason_category' => 'INCOMPLETE_CONTENT', 'recruiter_visible_note' => 'Lengkapi.',
        ])->assertOk()->assertJsonPath('data.current_status', 'REVISION_REQUIRED');

        $reject = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        $this->actingAs($superAdmin)->postJson("/vacancies/{$reject}/reject", [
            'reason_category' => 'POLICY_VIOLATION', 'recruiter_visible_note' => 'Ditolak.',
        ])->assertOk()->assertJsonPath('data.current_status', 'REJECTED');

        $approve = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => now()->subHour()->toIso8601String(), 'close_at' => now()->addDays(9)->toIso8601String(),
        ]);
        $this->actingAs($superAdmin)->postJson("/vacancies/{$approve}/approve")
            ->assertOk()->assertJsonPath('data.current_status', 'PUBLISHED');

        // No bypass of a source-status rule, and the reason rule is identical.
        $wrongSource = $this->vacancyAt($recruiter, $company, 'DRAFT');
        $this->actingAs($superAdmin)->postJson("/vacancies/{$wrongSource}/approve")
            ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');
        $noReason = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        $this->actingAs($superAdmin)->postJson("/vacancies/{$noReason}/reject", [])
            ->assertStatus(422)->assertJsonPath('error.code', 'REVIEW_REASON_REQUIRED');
    }

    public function test_a_moderator_who_is_an_active_member_of_the_owning_company_cannot_moderate_it(): void
    {
        foreach ([RoleCode::CareerCenterStaff, RoleCode::SuperAdmin] as $index => $role) {
            [$recruiter, $company] = $this->verifiedCompanyWithRecruiter("mod-conflict-{$index}@example.test");
            $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
                'open_at' => now()->subHour()->toIso8601String(), 'close_at' => now()->addDays(9)->toIso8601String(),
            ]);
            $conflicted = $this->moderator("mod-conflict-actor-{$index}@example.test", $role);
            $this->addMember((int) $company->id, $conflicted, 'COMPANY_ADMIN');

            foreach (['approve', 'reject', 'request-revision', 'suspend', 'restore'] as $action) {
                $this->actingAs($conflicted)->postJson("/vacancies/{$id}/{$action}", [
                    'reason_category' => 'X', 'recruiter_visible_note' => 'Y',
                ])->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
            }

            self::assertSame('PENDING_REVIEW', DB::table('vacancies')->where('id', $id)->value('current_status'));
            self::assertSame([], $this->reviewRows($id));
        }
    }

    public function test_a_revoked_membership_does_not_bar_a_moderator(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('mod-revoked@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => now()->subHour()->toIso8601String(), 'close_at' => now()->addDays(9)->toIso8601String(),
        ]);
        $moderator = $this->moderator('mod-revoked-cc@example.test');
        $this->addMember((int) $company->id, $moderator, 'COMPANY_ADMIN', 'REVOKED');

        // The bar is an ACTIVE membership; a revoked one is no membership at all.
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/approve")->assertOk();
    }

    public function test_recruiters_and_auditors_never_moderate(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('mod-deny@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => now()->subHour()->toIso8601String(), 'close_at' => now()->addDays(9)->toIso8601String(),
        ]);
        $auditor = $this->moderator('mod-deny-auditor@example.test', RoleCode::Auditor);

        foreach ([$recruiter, $auditor] as $actor) {
            foreach (['approve', 'reject', 'request-revision', 'suspend', 'restore'] as $action) {
                $this->actingAs($actor)->postJson("/vacancies/{$id}/{$action}", [
                    'reason_category' => 'X', 'recruiter_visible_note' => 'Y',
                ])->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
            }
        }

        self::assertSame('PENDING_REVIEW', DB::table('vacancies')->where('id', $id)->value('current_status'));
        self::assertSame([], $this->reviewRows($id));
    }

    public function test_moderation_history_is_append_only_across_a_full_cycle(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('mod-history@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'DRAFT', [
            'open_at' => now()->subHour()->toIso8601String(), 'close_at' => now()->addDays(9)->toIso8601String(),
        ]);
        $moderator = $this->moderator('mod-history-cc@example.test');

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")->assertOk();
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/request-revision", [
            'reason_category' => 'INCOMPLETE_CONTENT', 'recruiter_visible_note' => 'Lengkapi.',
        ])->assertOk();
        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/submit-review")->assertOk();
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/approve")->assertOk();

        $actions = array_column($this->reviewRows($id), 'action');
        self::assertSame(['SUBMIT', 'REQUEST_REVISION', 'SUBMIT', 'APPROVE'], $actions);
        // Every earlier row survives untouched: history is never rewritten.
        self::assertSame('REVISION_REQUIRED', $this->reviewRows($id)[1]['to_status']);
        self::assertSame(1, DB::table('vacancies')->count());
    }
}
