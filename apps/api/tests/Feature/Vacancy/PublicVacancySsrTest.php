<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Company\Enums\CompanyStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Browser-facing public vacancy pages (ADR-017 — Inertia SSR). These tests
 * exercise the same Inertia response the Node SSR renderer would receive —
 * component name, props, and status — proving business-layer parity with the
 * JSON API without invoking the external Node process (a separate, manual
 * end-to-end smoke test confirmed actual server-rendered HTML output; see the
 * completion report). `GetPublicVacancy` / `ListPublicVacancies` /
 * `PublicVacancyScope` are consumed directly here exactly as by the API
 * controller — no loopback HTTP call, no second visibility predicate.
 */
final class PublicVacancySsrTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Routes
    // ---------------------------------------------------------------

    public function test_web_routes_exist_and_require_no_authentication(): void
    {
        [, , , $slug] = $this->publicVacancy('ssr-routes@example.test');

        $this->get('/lowongan')->assertOk();
        $this->get("/lowongan/{$slug}")->assertOk();
    }

    public function test_web_route_uses_inertia_public_components(): void
    {
        [, , , $slug] = $this->publicVacancy('ssr-components@example.test');

        $this->get('/lowongan')->assertInertia(fn (Assert $page) => $page->component('public/VacancyList'));
        $this->get("/lowongan/{$slug}")->assertInertia(fn (Assert $page) => $page->component('public/VacancyDetail'));
    }

    // ---------------------------------------------------------------
    // API / SSR visibility parity — the full status matrix
    // ---------------------------------------------------------------

    public function test_api_and_ssr_visibility_parity_across_the_full_status_matrix(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('parity@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);

        $hiddenStatuses = ['DRAFT', 'PENDING_REVIEW', 'REVISION_REQUIRED', 'APPROVED', 'SCHEDULED', 'REJECTED', 'SUSPENDED', 'CLOSED', 'EXPIRED'];
        $slugs = [];
        foreach ($hiddenStatuses as $status) {
            $id = $this->vacancyAt($recruiter, $company, $status, [
                'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
            ]);
            $slugs[$status] = DB::table('vacancies')->where('id', $id)->value('slug');
        }
        $internalId = $this->publishedVacancy($recruiter, $company, $close, ['target_audience' => 'INTERNAL']);
        $slugs['INTERNAL'] = DB::table('vacancies')->where('id', $internalId)->value('slug');

        $visibleId = $this->publishedVacancy($recruiter, $company, $close);
        $slugs['PUBLISHED_ACTIVE'] = DB::table('vacancies')->where('id', $visibleId)->value('slug');

        Carbon::setTestNow($close->copy()->subDay());

        foreach ($slugs as $label => $slug) {
            $apiStatus = $this->getJson("/api/v1/public/vacancies/{$slug}")->getStatusCode();
            $webStatus = $this->get("/lowongan/{$slug}")->getStatusCode();

            if ($label === 'PUBLISHED_ACTIVE') {
                self::assertSame(200, $apiStatus, "API: {$label} must be visible.");
                self::assertSame(200, $webStatus, "SSR: {$label} must be visible.");
            } else {
                self::assertSame(404, $apiStatus, "API: {$label} must be hidden.");
                self::assertSame(404, $webStatus, "SSR: {$label} must be hidden.");
            }
        }

        $this->getJson('/api/v1/public/vacancies/does-not-exist-parity')->assertNotFound();
        $this->get('/lowongan/does-not-exist-parity')->assertNotFound();
    }

    public function test_api_and_ssr_visibility_parity_for_campus_and_date_boundary(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('parity-campus@example.test');
        $close = Carbon::parse('2026-12-05T09:00:00+00:00');
        $open = Carbon::parse('2026-11-25T09:00:00+00:00');

        // Campus fixture (never processed by company discovery).
        $unitId = DB::table('organizational_units')->insertGetId([
            'code' => 'UNIT-SSR-PARITY', 'name' => 'Unit Paritas', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $campusId = DB::table('vacancies')->insertGetId([
            'vacancy_code' => 'VAC-SSR-CAMPUS', 'slug' => 'ssr-campus-parity-fixture',
            'vacancy_type' => 'CAMPUS_EMPLOYMENT', 'ownership_type' => 'CAMPUS',
            'company_id' => null, 'organizational_unit_id' => $unitId,
            'title' => 'Staf Kampus Paritas', 'description' => 'Deskripsi.',
            'employment_type' => 'FULL_TIME', 'openings_count' => 1,
            'target_audience' => 'INTERNAL', 'application_method' => 'IN_PORTAL',
            'current_status' => 'PUBLISHED', 'created_by' => $recruiter->id,
            'open_at' => $open, 'close_at' => $close, 'published_at' => $open,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Non-VERIFIED company.
        [$hiddenRecruiter, $hiddenCompany] = $this->companyWithRecruiter('parity-nonverified@example.test', CompanyStatus::Verified);
        $hiddenId = $this->publishedVacancy($hiddenRecruiter, $hiddenCompany, $close, ['open_at' => $open->toIso8601String()]);
        DB::table('companies')->where('id', $hiddenCompany->id)->update(['verification_status' => 'SUSPENDED']);
        $hiddenSlug = DB::table('vacancies')->where('id', $hiddenId)->value('slug');

        $activeId = $this->publishedVacancy($recruiter, $company, $close, ['open_at' => $open->toIso8601String()]);
        $activeSlug = DB::table('vacancies')->where('id', $activeId)->value('slug');

        // Before open_at.
        Carbon::setTestNow($open->copy()->subDay());
        $this->getJson("/api/v1/public/vacancies/{$activeSlug}")->assertNotFound();
        $this->get("/lowongan/{$activeSlug}")->assertNotFound();

        // At close_at (exclusive boundary — hidden).
        Carbon::setTestNow($close);
        $this->getJson("/api/v1/public/vacancies/{$activeSlug}")->assertNotFound();
        $this->get("/lowongan/{$activeSlug}")->assertNotFound();

        // Back to active window.
        Carbon::setTestNow($close->copy()->subDay());
        $this->getJson("/api/v1/public/vacancies/{$activeSlug}")->assertOk();
        $this->get("/lowongan/{$activeSlug}")->assertOk();

        $this->getJson('/api/v1/public/vacancies/ssr-campus-parity-fixture')->assertNotFound();
        $this->get('/lowongan/ssr-campus-parity-fixture')->assertNotFound();

        $this->getJson("/api/v1/public/vacancies/{$hiddenSlug}")->assertNotFound();
        $this->get("/lowongan/{$hiddenSlug}")->assertNotFound();

        self::assertSame('PUBLISHED', DB::table('vacancies')->where('id', $campusId)->value('current_status'));
    }

    // ---------------------------------------------------------------
    // PD-1 — SSR must show the same company-verification behaviour as API
    // ---------------------------------------------------------------

    public function test_pd1_ssr_hides_on_suspension_and_reveals_on_restore_with_no_vacancy_mutation(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('pd1-ssr@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $id = $this->publishedVacancy($recruiter, $company, $close);
        $slug = DB::table('vacancies')->where('id', $id)->value('slug');
        $publishedAtBefore = DB::table('vacancies')->where('id', $id)->value('published_at');
        $reviewsBefore = $this->reviewRows($id);
        $auditBefore = DB::table('audit_logs')->where('object_type', 'vacancy')->where('object_id', $id)->count();

        Carbon::setTestNow($close->copy()->subDay());

        // VERIFIED + active: SSR visible.
        $this->get("/lowongan/{$slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/VacancyDetail')->where('vacancy.slug', $slug));

        // Company SUSPENDED: SSR hidden, mirrors API 404.
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'SUSPENDED']);
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertNotFound();
        $this->get("/lowongan/{$slug}")->assertNotFound();

        // No vacancy transition, no moderation/audit side effect from a read.
        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('PUBLISHED', $row->current_status);
        self::assertSame($publishedAtBefore, $row->published_at);
        self::assertSame($reviewsBefore, $this->reviewRows($id));
        self::assertSame($auditBefore, DB::table('audit_logs')->where('object_type', 'vacancy')->where('object_id', $id)->count());

        // Restore to VERIFIED while still active: visible again on both surfaces.
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'VERIFIED']);
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk();
        $this->get("/lowongan/{$slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/VacancyDetail'));
        self::assertSame('PUBLISHED', DB::table('vacancies')->where('id', $id)->value('current_status'));
    }

    // ---------------------------------------------------------------
    // SSR hydration payload privacy
    // ---------------------------------------------------------------

    public function test_ssr_hydration_payload_contains_no_sensitive_fields(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('ssr-privacy@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $id = $this->publishedVacancy($recruiter, $company, $close, [
            'application_method' => 'EXTERNAL_ATS', 'external_ats_url' => 'https://ats.example.test/apply',
            'salary_min' => 5000000, 'salary_max' => 9000000,
        ]);
        $slug = DB::table('vacancies')->where('id', $id)->value('slug');

        Carbon::setTestNow($close->copy()->subDay());
        $body = $this->get("/lowongan/{$slug}")->assertOk()->getContent();

        foreach ([
            'internal_note', 'reviewer_user_id', 'created_by', 'legal_identifier', 'company_role',
            'candidate_profile_id', 'application_code', 'screening_question', 'external_ats_url',
            'ats.example.test', 'logo_storage_reference', 'salary_min', 'salary_max', 'verification_status',
            'audit_logs', 'outbox',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $body, "SSR hydration payload must never contain '{$forbidden}'.");
        }

        $listBody = $this->get('/lowongan')->assertOk()->getContent();
        self::assertStringNotContainsString('internal_note', $listBody);
        self::assertStringNotContainsString('salary_min', $listBody);
        self::assertStringNotContainsString('verification_status', $listBody);
    }

    // ---------------------------------------------------------------
    // External ATS / IN_PORTAL — no side effects from a GET
    // ---------------------------------------------------------------

    public function test_external_ats_ssr_detail_discloses_state_without_raw_url_or_side_effects(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('ssr-external@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $id = $this->publishedVacancy($recruiter, $company, $close, [
            'application_method' => 'EXTERNAL_ATS', 'external_ats_url' => 'https://careers.example.test/job/1',
        ]);
        $slug = DB::table('vacancies')->where('id', $id)->value('slug');
        $eventsBefore = DB::table('external_apply_events')->count();
        $applicationsBefore = DB::table('applications')->count();

        Carbon::setTestNow($close->copy()->subDay());
        $this->get("/lowongan/{$slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vacancy.application_method', 'EXTERNAL_ATS')
                ->where('vacancy.applies_externally', true)
                ->missing('vacancy.external_ats_url'));

        self::assertSame($eventsBefore, DB::table('external_apply_events')->count());
        self::assertSame($applicationsBefore, DB::table('applications')->count());
    }

    // ---------------------------------------------------------------
    // Filters
    // ---------------------------------------------------------------

    public function test_ssr_listing_consumes_the_same_frozen_filter_vocabulary(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('ssr-filters@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $target = $this->publishedVacancy($recruiter, $company, $close, [
            'vacancy_type' => 'INTERNSHIP', 'employment_type' => 'INTERNSHIP', 'workplace_mode' => 'REMOTE',
            'target_audience' => 'ALUMNI_ONLY',
        ]);
        $other = $this->publishedVacancy($recruiter, $company, $close, ['target_audience' => 'PUBLIC']);
        $targetSlug = DB::table('vacancies')->where('id', $target)->value('slug');
        $otherSlug = DB::table('vacancies')->where('id', $other)->value('slug');

        Carbon::setTestNow($close->copy()->subDay());

        $this->get('/lowongan?vacancy_type=INTERNSHIP')
            ->assertInertia(fn (Assert $page) => $page->has('items', 1, fn (Assert $item) => $item->where('slug', $targetSlug)->etc()));

        // Unsupported filters gain no web-only semantics: the request still
        // succeeds and simply ignores what is not in the allow-list, never
        // silently supporting it.
        $this->get('/lowongan?minimum_education=S1')->assertOk();
        $this->get('/lowongan?experience_requirement=5')->assertOk();
        $this->get('/lowongan?salary=1000000')->assertOk();

        self::assertNotSame($targetSlug, $otherSlug);
    }

    // ---------------------------------------------------------------
    // Pagination / bounded result set
    // ---------------------------------------------------------------

    public function test_ssr_listing_never_materializes_the_full_result_set(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('ssr-pagination@example.test');
        $close = Carbon::parse('2026-12-10T09:00:00+00:00');
        for ($i = 0; $i < 5; $i++) {
            $this->publishedVacancy($recruiter, $company, $close, ['title' => "SSR Page Vacancy {$i}"]);
        }
        Carbon::setTestNow($close->copy()->subDay());

        $this->get('/lowongan?per_page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 2)
                ->where('pagination.per_page', 2)
                ->where('pagination.total', 5));
    }

    // ---------------------------------------------------------------
    // Query safety / N+1
    // ---------------------------------------------------------------

    public function test_ssr_listing_query_count_is_bounded(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('ssr-n1@example.test');
        $close = Carbon::parse('2026-12-15T09:00:00+00:00');
        for ($i = 0; $i < 6; $i++) {
            $this->publishedVacancy($recruiter, $company, $close, ['title' => "SSR N1 Vacancy {$i}"]);
        }
        Carbon::setTestNow($close->copy()->subDay());

        DB::enableQueryLog();
        $this->get('/lowongan?per_page=2')->assertOk();
        $countFor2 = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->get('/lowongan?per_page=6')->assertOk();
        $countFor6 = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertLessThanOrEqual($countFor2 + 2, $countFor6, 'SSR listing query count must not grow linearly with result-set size.');
    }

    // ---------------------------------------------------------------
    // No competing routes / rate limiting scope
    // ---------------------------------------------------------------

    public function test_no_second_public_vacancy_web_route_or_directory_is_introduced(): void
    {
        $uris = collect(Route::getRoutes())->map(static fn ($route): string => $route->uri());

        self::assertSame(1, $uris->filter(fn (string $u): bool => $u === 'lowongan')->count());
        self::assertSame(1, $uris->filter(fn (string $u): bool => $u === 'lowongan/{slug}')->count());
        // The web pages and the VERSIONED_API route are two distinct,
        // legitimate surfaces for the same read layer — this only asserts
        // neither web route was accidentally registered a second time.
        self::assertTrue($uris->contains('api/v1/public/vacancies'), 'The API route remains a separate, legitimate surface.');
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    /** @return array{0: \App\Domains\Identity\Models\User, 1: \App\Domains\Company\Models\Company, 2: int, 3: string} */
    private function publicVacancy(string $email): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $id = $this->publishedVacancy($recruiter, $company, $close);
        Carbon::setTestNow($close->copy()->subDay());

        return [$recruiter, $company, $id, DB::table('vacancies')->where('id', $id)->value('slug')];
    }

    private function publishedVacancy($recruiter, $company, Carbon $close, array $overrides = []): int
    {
        $open = $overrides['open_at'] ?? $close->copy()->subDays(20)->toIso8601String();
        unset($overrides['open_at']);
        $id = $this->vacancyAt($recruiter, $company, 'PUBLISHED', array_merge([
            'open_at' => $open, 'close_at' => $close->toIso8601String(),
        ], $overrides));
        DB::table('vacancies')->where('id', $id)->update(['published_at' => $open]);

        return $id;
    }
}
