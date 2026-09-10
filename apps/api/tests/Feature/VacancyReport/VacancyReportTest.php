<?php

declare(strict_types=1);

namespace Tests\Feature\VacancyReport;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * PGC-V1 / PD-C — Laporkan Lowongan public anti-fraud reporting.
 */
final class VacancyReportTest extends VacancyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('vacancy-report:ip:'.hash('sha256', '127.0.0.1'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function publishedSlug(string $email): string
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(10);
        $id = $this->vacancyAt($recruiter, $company, 'PUBLISHED', [
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ]);
        DB::table('vacancies')->where('id', $id)->update(['published_at' => $open]);
        Carbon::setTestNow($close->copy()->subDay());

        // The fixture helpers authenticate a recruiter; clear that so the
        // "public" report requests below are genuinely anonymous.
        $this->app['auth']->forgetGuards();

        return (string) DB::table('vacancies')->where('id', $id)->value('slug');
    }

    private function careerCenter(string $email, RoleCode $role = RoleCode::CareerCenterStaff): User
    {
        $u = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($u, $role);

        return $u;
    }

    public function test_anonymous_report_is_accepted_and_identity_is_never_public(): void
    {
        $slug = $this->publishedSlug('vr-anon@example.test');

        $this->postJson("/lowongan/{$slug}/laporkan", [
            'reason' => 'FRAUD_OR_SCAM',
            'details' => 'Meminta pembayaran di muka.',
            'reporter_name' => 'Warga',
            'reporter_email' => 'warga@example.test',
        ])->assertCreated()->assertJsonPath('data.status', 'NEW');

        $row = DB::table('vacancy_reports')->latest('id')->first();
        self::assertNull($row->reporter_user_id);
        self::assertSame('Warga', $row->reporter_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vacancy_report_created', 'object_id' => $row->id]);
        // Audit payload carries the reason category, not the reporter identity.
        $summary = (string) DB::table('audit_logs')->where('action', 'vacancy_report_created')->where('object_id', $row->id)->value('change_summary');
        self::assertStringNotContainsString('warga@example.test', $summary);
    }

    public function test_authenticated_report_attaches_the_user_and_ignores_client_identity(): void
    {
        $slug = $this->publishedSlug('vr-auth@example.test');
        $candidate = $this->makeUser('vr-reporter@example.test', UserStatus::Active);
        $this->assignRole($candidate, RoleCode::CandidateExternal);

        $this->actingAs($candidate)->postJson("/lowongan/{$slug}/laporkan", [
            'reason' => 'MISLEADING_INFORMATION',
            'reporter_name' => 'ignored', 'reporter_email' => 'ignored@example.test',
        ])->assertCreated();

        $row = DB::table('vacancy_reports')->latest('id')->first();
        self::assertSame((int) $candidate->id, (int) $row->reporter_user_id);
        self::assertNull($row->reporter_name);
        self::assertNull($row->reporter_email);
    }

    public function test_reason_other_requires_details_and_a_nonexistent_slug_is_a_safe_404(): void
    {
        $slug = $this->publishedSlug('vr-validate@example.test');

        $this->postJson("/lowongan/{$slug}/laporkan", ['reason' => 'OTHER'])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->postJson('/lowongan/tidak-ada-lowongan-ini/laporkan', ['reason' => 'FRAUD_OR_SCAM'])
            ->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_anonymous_rate_limit_is_five_per_hour(): void
    {
        $slug = $this->publishedSlug('vr-rate@example.test');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/lowongan/{$slug}/laporkan", ['reason' => 'FRAUD_OR_SCAM'])->assertCreated();
        }
        $this->postJson("/lowongan/{$slug}/laporkan", ['reason' => 'FRAUD_OR_SCAM'])
            ->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_career_center_owns_the_lifecycle_and_recruiters_cannot_review(): void
    {
        $slug = $this->publishedSlug('vr-lifecycle@example.test');
        $this->postJson("/lowongan/{$slug}/laporkan", ['reason' => 'INAPPROPRIATE_CONTENT'])->assertCreated();
        $reportId = (int) DB::table('vacancy_reports')->latest('id')->value('id');

        [$recruiter] = $this->verifiedCompanyWithRecruiter('vr-recruiter@example.test');
        $this->actingAs($recruiter)->getJson('/moderasi-lowongan/laporan/data')->assertStatus(403);
        $this->actingAs($recruiter)->postJson("/vacancy-reports/{$reportId}/review")->assertStatus(403);

        $cc = $this->careerCenter('vr-cc@example.test');
        $this->actingAs($cc)->getJson('/moderasi-lowongan/laporan/data')->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
        $this->actingAs($cc)->postJson("/vacancy-reports/{$reportId}/review")
            ->assertOk()->assertJsonPath('data.status', 'UNDER_REVIEW');
        $this->actingAs($cc)->postJson("/vacancy-reports/{$reportId}/action", ['note' => 'Lowongan ditangguhkan.'])
            ->assertOk()->assertJsonPath('data.status', 'ACTIONED');

        // Terminal — a further transition is a 409.
        $this->actingAs($cc)->postJson("/vacancy-reports/{$reportId}/dismiss")
            ->assertStatus(409)->assertJsonPath('error.code', 'CONFLICT');

        foreach (['vacancy_report_created', 'vacancy_report_review_started', 'vacancy_report_actioned'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'object_id' => $reportId]);
        }
    }

    public function test_a_career_center_reviewer_cannot_review_a_report_they_filed(): void
    {
        $slug = $this->publishedSlug('vr-self@example.test');
        $cc = $this->careerCenter('vr-selfcc@example.test');

        $this->actingAs($cc)->postJson("/lowongan/{$slug}/laporkan", ['reason' => 'SUSPICIOUS_EXTERNAL_LINK'])->assertCreated();
        $reportId = (int) DB::table('vacancy_reports')->latest('id')->value('id');

        $this->actingAs($cc)->postJson("/vacancy-reports/{$reportId}/review")
            ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_super_admin_may_read_the_queue_but_not_transition(): void
    {
        $slug = $this->publishedSlug('vr-sa@example.test');
        $this->postJson("/lowongan/{$slug}/laporkan", ['reason' => 'FRAUD_OR_SCAM'])->assertCreated();
        $reportId = (int) DB::table('vacancy_reports')->latest('id')->value('id');

        $sa = $this->makeUser('vr-superadmin@example.test', UserStatus::Active);
        $this->assignRole($sa, RoleCode::SuperAdmin);

        $this->actingAs($sa)->getJson('/moderasi-lowongan/laporan/data')->assertOk();
        $this->actingAs($sa)->postJson("/vacancy-reports/{$reportId}/review")->assertStatus(403);
    }
}
