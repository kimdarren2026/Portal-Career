<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Company\Enums\CompanyStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * Public Vacancy Discovery Foundation — GET /api/v1/public/vacancies,
 * .../{slug}, and .../reference-data. No authentication is used anywhere in
 * this file: every request is anonymous, matching the frozen "Authentication:
 * None" contract.
 */
final class PublicVacancyDiscoveryTest extends VacancyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The per-IP limiter is Redis-backed and NOT reset by the per-test
        // database transaction; every HTTP test client request shares the
        // same fixed IP (127.0.0.1), so it must be cleared between tests to
        // avoid cross-test bleed unrelated to the dedicated rate-limit test.
        RateLimiter::clear('public-discovery:ip:'.hash('sha256', '127.0.0.1'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Routes
    // ---------------------------------------------------------------

    public function test_public_routes_exist_and_require_no_authentication(): void
    {
        [, , $id, $slug] = $this->publicVacancy('routes@example.test');

        $this->getJson('/api/v1/public/vacancies')->assertOk();
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk();
        $this->getJson('/api/v1/public/reference-data')->assertOk();
    }

    public function test_no_manual_expire_publish_or_report_route_exists(): void
    {
        $uris = collect(Route::getRoutes())->map(static fn ($route): string => $route->uri());

        self::assertFalse($uris->contains(fn (string $u): bool => str_contains($u, 'public/vacancies/') && str_contains($u, 'expire')));
        self::assertFalse($uris->contains(fn (string $u): bool => str_contains($u, 'public') && str_contains($u, 'report')));
        self::assertNull(Route::getRoutes()->getByName('public.vacancies.publish'));
        // PD-2 makes the single-company endpoint resolvable but explicitly does
        // NOT create a Public Company Directory — the bare, slug-less listing
        // route must never exist, while the single-company route now does.
        self::assertFalse($uris->contains('api/v1/public/companies'));
        self::assertTrue($uris->contains('api/v1/public/companies/{slug}'));
    }

    // ---------------------------------------------------------------
    // Visibility: status matrix, ownership, audience
    // ---------------------------------------------------------------

    public function test_visibility_status_matrix(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('matrix@example.test');
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);

        $hiddenStatuses = ['DRAFT', 'PENDING_REVIEW', 'REVISION_REQUIRED', 'APPROVED', 'SCHEDULED', 'REJECTED', 'SUSPENDED', 'CLOSED', 'EXPIRED'];
        $ids = [];
        foreach ($hiddenStatuses as $status) {
            $ids[$status] = $this->vacancyAt($recruiter, $company, $status, [
                'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
            ]);
        }
        $publishedId = $this->vacancyAt($recruiter, $company, 'PUBLISHED', [
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ]);

        Carbon::setTestNow($close->copy()->subDay());
        $slugs = collect($ids)->map(fn ($id) => DB::table('vacancies')->where('id', $id)->value('slug'));
        $visibleSlugs = $this->getJson('/api/v1/public/vacancies?per_page=50')
            ->assertOk()->json('data.items.*.slug');

        foreach ($hiddenStatuses as $status) {
            self::assertNotContains($slugs[$status], $visibleSlugs, "{$status} must not be publicly listed.");
        }
        self::assertContains(DB::table('vacancies')->where('id', $publishedId)->value('slug'), $visibleSlugs);
    }

    public function test_campus_ownership_is_never_publicly_visible(): void
    {
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        [$recruiter] = $this->verifiedCompanyWithRecruiter('campus-hidden@example.test');
        $unitId = DB::table('organizational_units')->insertGetId([
            'code' => 'UNIT-PUB', 'name' => 'Unit Publik', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $campusId = DB::table('vacancies')->insertGetId([
            'vacancy_code' => 'VAC-CAMPUS-PUB', 'slug' => 'campus-public-fixture',
            'vacancy_type' => 'CAMPUS_EMPLOYMENT', 'ownership_type' => 'CAMPUS',
            'company_id' => null, 'organizational_unit_id' => $unitId,
            'title' => 'Staf Kampus Publik', 'description' => 'Deskripsi.',
            'employment_type' => 'FULL_TIME', 'openings_count' => 1,
            'target_audience' => 'PUBLIC', 'application_method' => 'IN_PORTAL',
            'current_status' => 'PUBLISHED', 'created_by' => $recruiter->id,
            'open_at' => $close->copy()->subDays(20), 'close_at' => $close,
            'published_at' => $close->copy()->subDays(20),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Carbon::setTestNow($close->copy()->subDay());
        $this->getJson('/api/v1/public/vacancies/campus-public-fixture')->assertNotFound()
            ->assertJsonPath('error.code', 'VACANCY_NOT_PUBLIC');
        $slugs = $this->getJson('/api/v1/public/vacancies?per_page=50')->assertOk()->json('data.items.*.slug');
        self::assertNotContains('campus-public-fixture', $slugs);
    }

    public function test_internal_audience_is_hidden_public_audience_is_visible(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('audience@example.test');
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        $internalId = $this->publishedVacancy($recruiter, $company, $close, ['target_audience' => 'INTERNAL']);
        $publicId = $this->publishedVacancy($recruiter, $company, $close, ['target_audience' => 'PUBLIC']);
        $alumniId = $this->publishedVacancy($recruiter, $company, $close, ['target_audience' => 'ALUMNI_ONLY']);
        $finalYearId = $this->publishedVacancy($recruiter, $company, $close, ['target_audience' => 'FINAL_YEAR_AND_ALUMNI']);

        Carbon::setTestNow($close->copy()->subDay());
        $internalSlug = DB::table('vacancies')->where('id', $internalId)->value('slug');
        $this->getJson("/api/v1/public/vacancies/{$internalSlug}")->assertNotFound();

        $slugs = $this->getJson('/api/v1/public/vacancies?per_page=50')->assertOk()->json('data.items.*.slug');
        self::assertNotContains($internalSlug, $slugs);
        foreach ([$publicId, $alumniId, $finalYearId] as $id) {
            self::assertContains(DB::table('vacancies')->where('id', $id)->value('slug'), $slugs);
        }
    }

    // ---------------------------------------------------------------
    // Date boundary — independently enforced, never assumed from O-7
    // ---------------------------------------------------------------

    public function test_date_boundary_is_independently_enforced(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('boundary@example.test');
        $open = Carbon::parse('2026-11-10T09:00:00+00:00');
        $close = Carbon::parse('2026-11-20T09:00:00+00:00');
        $id = $this->publishedVacancy($recruiter, $company, $close, ['open_at' => $open->toIso8601String()]);
        $slug = DB::table('vacancies')->where('id', $id)->value('slug');

        Carbon::setTestNow($open->copy()->subSecond());
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertNotFound();

        Carbon::setTestNow($open);
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk();

        Carbon::setTestNow($close->copy()->subSecond());
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk();

        Carbon::setTestNow($close);
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertNotFound();

        Carbon::setTestNow($close->copy()->addSecond());
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Direct detail privacy — uniform 404, no state-specific error body
    // ---------------------------------------------------------------

    public function test_direct_detail_privacy_is_uniform(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('detail-privacy@example.test');
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);

        $publicId = $this->publishedVacancy($recruiter, $company, $close);
        Carbon::setTestNow($close->copy()->subDay());
        $this->getJson('/api/v1/public/vacancies/does-not-exist-abc123')
            ->assertNotFound()->assertJsonPath('error.code', 'VACANCY_NOT_PUBLIC');

        foreach (['DRAFT', 'SCHEDULED', 'SUSPENDED', 'EXPIRED'] as $status) {
            $id = $this->vacancyAt($recruiter, $company, $status, [
                'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
            ]);
            $slug = DB::table('vacancies')->where('id', $id)->value('slug');
            $response = $this->getJson("/api/v1/public/vacancies/{$slug}");
            $response->assertNotFound()->assertJsonPath('error.code', 'VACANCY_NOT_PUBLIC');
            self::assertArrayNotHasKey('details', $response->json('error'), "{$status} must not leak a state-specific error body.");
        }

        $internalId = $this->publishedVacancy($recruiter, $company, $close, ['target_audience' => 'INTERNAL']);
        $this->getJson('/api/v1/public/vacancies/'.DB::table('vacancies')->where('id', $internalId)->value('slug'))
            ->assertNotFound()->assertJsonPath('error.code', 'VACANCY_NOT_PUBLIC');

        [$unverifiedRecruiter, $unverified] = $this->companyWithRecruiter('detail-privacy-unverified@example.test', CompanyStatus::Verified);
        $nonVerifiedId = $this->publishedVacancy($unverifiedRecruiter, $unverified, $close);
        DB::table('companies')->where('id', $unverified->id)->update(['verification_status' => 'SUSPENDED', 'suspended_at' => now()]);
        $this->getJson('/api/v1/public/vacancies/'.DB::table('vacancies')->where('id', $nonVerifiedId)->value('slug'))
            ->assertNotFound()->assertJsonPath('error.code', 'VACANCY_NOT_PUBLIC');

        $this->getJson('/api/v1/public/vacancies/'.DB::table('vacancies')->where('id', $publicId)->value('slug'))->assertOk();
    }

    // ---------------------------------------------------------------
    // PD-1 — company verification gate on public visibility
    // ---------------------------------------------------------------

    public function test_pd1_company_suspension_hides_and_restore_reveals_published_vacancy(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('pd1@example.test');
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        $id = $this->publishedVacancy($recruiter, $company, $close);
        $slug = DB::table('vacancies')->where('id', $id)->value('slug');
        $publishedAtBefore = DB::table('vacancies')->where('id', $id)->value('published_at');
        $reviewsBefore = $this->reviewRows($id);
        $auditBefore = DB::table('audit_logs')->where('object_type', 'vacancy')->where('object_id', $id)->count();
        $candidate = $this->makeUser('pd1-candidate@example.test');
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidate->id, 'current_candidate_type' => 'EXTERNAL', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('applications')->insert([
            'application_code' => 'APP-PD1-'.$id, 'candidate_profile_id' => $profileId, 'vacancy_id' => $id,
            'current_status' => 'APPLIED', 'first_applied_at' => now(), 'reopen_count' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $applicationBefore = (array) DB::table('applications')->where('vacancy_id', $id)->first();

        Carbon::setTestNow($close->copy()->subDay());

        // 1. VERIFIED + active PUBLISHED: visible.
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk();
        self::assertContains($slug, $this->getJson('/api/v1/public/vacancies?per_page=50')->json('data.items.*.slug'));

        // 2 & 3. Company SUSPENDED: disappears from listing and detail (404).
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'SUSPENDED', 'suspended_at' => now()]);
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertNotFound()->assertJsonPath('error.code', 'VACANCY_NOT_PUBLIC');
        self::assertNotContains($slug, $this->getJson('/api/v1/public/vacancies?per_page=50')->json('data.items.*.slug'));

        // 4, 5, 6, 7, 8. No vacancy state was mutated by a PD-1 read decision.
        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('PUBLISHED', $row->current_status);
        self::assertSame($publishedAtBefore, $row->published_at);
        self::assertSame($reviewsBefore, $this->reviewRows($id));
        self::assertSame($auditBefore, DB::table('audit_logs')->where('object_type', 'vacancy')->where('object_id', $id)->count());
        self::assertSame($applicationBefore, (array) DB::table('applications')->where('vacancy_id', $id)->first());

        // 9. Company restored to VERIFIED while vacancy still active: visible again,
        //    with no vacancy transition — this is not a republish/restore/reapprove.
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'VERIFIED', 'suspended_at' => null]);
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk();
        self::assertSame('PUBLISHED', DB::table('vacancies')->where('id', $id)->value('current_status'));
    }

    public function test_pd1_company_restored_after_vacancy_expired_stays_hidden(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('pd1-expired@example.test');
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        $id = $this->publishedVacancy($recruiter, $company, $close);
        $slug = DB::table('vacancies')->where('id', $id)->value('slug');

        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'SUSPENDED']);
        DB::table('vacancies')->where('id', $id)->update(['current_status' => 'EXPIRED']);
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'VERIFIED']);

        Carbon::setTestNow($close->copy()->addDay());
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertNotFound()->assertJsonPath('error.code', 'VACANCY_NOT_PUBLIC');
    }

    public function test_pd1_verified_non_partner_is_visible_and_partnership_alone_does_not_affect_visibility(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('pd1-nonpartner@example.test');
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        $id = $this->publishedVacancy($recruiter, $company, $close);
        $slug = DB::table('vacancies')->where('id', $id)->value('slug');

        Carbon::setTestNow($close->copy()->subDay());
        $response = $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk();
        self::assertFalse($response->json('data.company.mitra_kampus_active'));

        DB::table('partnerships')->insert([
            'company_id' => $company->id, 'partnership_type' => 'RECRUITMENT', 'agreement_number' => 'AGR-1',
            'start_date' => now()->subYear()->toDateString(),
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $response = $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk();
        self::assertTrue($response->json('data.company.mitra_kampus_active'));

        DB::table('partnerships')->where('company_id', $company->id)->update(['status' => 'EXPIRED']);
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk();
    }

    // ---------------------------------------------------------------
    // Filters
    // ---------------------------------------------------------------

    public function test_each_frozen_filter_narrows_results(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('filters@example.test');
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        $target = $this->publishedVacancy($recruiter, $company, $close, [
            'vacancy_type' => 'INTERNSHIP', 'employment_type' => 'INTERNSHIP', 'workplace_mode' => 'REMOTE',
            'target_audience' => 'ALUMNI_ONLY',
        ]);
        $other = $this->publishedVacancy($recruiter, $company, $close, [
            'vacancy_type' => 'COMPANY_EMPLOYMENT', 'employment_type' => 'FULL_TIME', 'workplace_mode' => 'ONSITE',
            'target_audience' => 'PUBLIC',
        ]);
        $targetSlug = DB::table('vacancies')->where('id', $target)->value('slug');
        $otherSlug = DB::table('vacancies')->where('id', $other)->value('slug');

        Carbon::setTestNow($close->copy()->subDay());

        foreach ([
            'vacancy_type=INTERNSHIP', 'employment_type=INTERNSHIP', 'workplace_mode=REMOTE',
            'target_audience=ALUMNI_ONLY', "company_id={$company->id}",
        ] as $qs) {
            $slugs = $this->getJson("/api/v1/public/vacancies?{$qs}")->assertOk()->json('data.items.*.slug');
            self::assertContains($targetSlug, $slugs, $qs);
        }

        $slugs = $this->getJson('/api/v1/public/vacancies?vacancy_type=INTERNSHIP')->assertOk()->json('data.items.*.slug');
        self::assertNotContains($otherSlug, $slugs);
    }

    public function test_unsupported_filters_are_rejected(): void
    {
        foreach (['minimum_education', 'experience_requirement', 'salary_min', 'not_a_real_filter'] as $filter) {
            $this->getJson("/api/v1/public/vacancies?{$filter}=1")
                ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        }
    }

    // ---------------------------------------------------------------
    // Search
    // ---------------------------------------------------------------

    public function test_search_respects_visibility_and_matches_title(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('search@example.test');
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        $match = $this->publishedVacancy($recruiter, $company, $close, ['title' => 'Backend Engineer Golang']);
        $noMatch = $this->publishedVacancy($recruiter, $company, $close, ['title' => 'Marketing Specialist']);
        $matchSlug = DB::table('vacancies')->where('id', $match)->value('slug');
        $noMatchSlug = DB::table('vacancies')->where('id', $noMatch)->value('slug');

        [$unverifiedRecruiter, $unverified] = $this->companyWithRecruiter('search-unverified@example.test', CompanyStatus::Verified);
        $hiddenId = $this->publishedVacancy($unverifiedRecruiter, $unverified, $close, ['title' => 'Backend Engineer Hidden']);
        DB::table('companies')->where('id', $unverified->id)->update(['verification_status' => 'SUSPENDED']);
        $internalId = $this->publishedVacancy($recruiter, $company, $close, ['title' => 'Backend Engineer Internal', 'target_audience' => 'INTERNAL']);

        Carbon::setTestNow($close->copy()->subDay());
        $slugs = $this->getJson('/api/v1/public/vacancies?q=backend')->assertOk()->json('data.items.*.slug');

        self::assertContains($matchSlug, $slugs);
        self::assertNotContains($noMatchSlug, $slugs);
        self::assertNotContains(DB::table('vacancies')->where('id', $hiddenId)->value('slug'), $slugs, 'Search must not surface a non-VERIFIED-company vacancy.');
        self::assertNotContains(DB::table('vacancies')->where('id', $internalId)->value('slug'), $slugs, 'Search must not surface an INTERNAL vacancy.');
    }

    // ---------------------------------------------------------------
    // Sort
    // ---------------------------------------------------------------

    public function test_sort_default_and_alternates(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('sort@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        Carbon::setTestNow($close->copy()->subDays(30));
        $older = $this->publishedVacancy($recruiter, $company, $close, ['title' => 'A Title']);
        Carbon::setTestNow($close->copy()->subDays(20));
        $newer = $this->publishedVacancy($recruiter, $company, $close, ['title' => 'B Title']);

        Carbon::setTestNow($close->copy()->subDay());
        $slugs = $this->getJson('/api/v1/public/vacancies')->assertOk()->json('data.items.*.slug');
        $olderSlug = DB::table('vacancies')->where('id', $older)->value('slug');
        $newerSlug = DB::table('vacancies')->where('id', $newer)->value('slug');
        self::assertTrue(array_search($newerSlug, $slugs, true) < array_search($olderSlug, $slugs, true), 'Default sort is published_at DESC.');

        $titleAsc = $this->getJson('/api/v1/public/vacancies?sort=title&direction=asc')->assertOk()->json('data.items.*.slug');
        self::assertTrue(array_search($olderSlug, $titleAsc, true) < array_search($newerSlug, $titleAsc, true));

        $this->getJson('/api/v1/public/vacancies?sort=close_at')->assertOk();
        $this->getJson('/api/v1/public/vacancies?sort=internal_note')->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    // ---------------------------------------------------------------
    // Pagination
    // ---------------------------------------------------------------

    public function test_pagination_is_bounded_and_stable(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('page@example.test');
        $close = Carbon::parse('2026-12-15T09:00:00+00:00');
        for ($i = 0; $i < 5; $i++) {
            $this->publishedVacancy($recruiter, $company, $close, ['title' => "Page Vacancy {$i}"]);
        }

        Carbon::setTestNow($close->copy()->subDay());
        $page1 = $this->getJson('/api/v1/public/vacancies?per_page=2')->assertOk();
        self::assertCount(2, $page1->json('data.items'));
        self::assertSame(2, $page1->json('data.pagination.per_page'));

        $huge = $this->getJson('/api/v1/public/vacancies?per_page=999999')->assertOk();
        self::assertLessThanOrEqual(50, count($huge->json('data.items')));

        $cursorPage1 = $this->getJson('/api/v1/public/vacancies?per_page=2&cursor='.'')->assertOk();
        $next = $cursorPage1->json('data.pagination.next_cursor');
        if ($next !== null) {
            $cursorPage2 = $this->getJson('/api/v1/public/vacancies?per_page=2&cursor='.$next)->assertOk();
            self::assertNotEquals($cursorPage1->json('data.items.0.slug'), $cursorPage2->json('data.items.0.slug'));
        }
    }

    // ---------------------------------------------------------------
    // Payload privacy
    // ---------------------------------------------------------------

    public function test_public_payload_never_contains_sensitive_fields(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('privacy@example.test');
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        $id = $this->publishedVacancy($recruiter, $company, $close, [
            'application_method' => 'EXTERNAL_ATS', 'external_ats_url' => 'https://ats.example.test/apply',
        ]);
        $slug = DB::table('vacancies')->where('id', $id)->value('slug');

        Carbon::setTestNow($close->copy()->subDay());
        $body = $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk()->getContent();

        foreach ([
            'internal_note', 'reviewer_user_id', 'created_by', 'legal_identifier', 'company_role',
            'candidate_profile_id', 'application_code', 'screening_questions', 'external_ats_url',
            'ats.example.test', 'logo_storage_reference', 'outbox', 'audit_logs', 'salary_min', 'salary_max',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $body, "Public payload must never contain '{$forbidden}'.");
        }

        $listBody = $this->getJson('/api/v1/public/vacancies')->assertOk()->getContent();
        self::assertStringNotContainsString('internal_note', $listBody);
        self::assertStringNotContainsString('salary_min', $listBody);
    }

    // ---------------------------------------------------------------
    // Company payload
    // ---------------------------------------------------------------

    public function test_company_summary_contains_only_safe_fields(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('company-summary@example.test');
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        $id = $this->publishedVacancy($recruiter, $company, $close);
        $slug = DB::table('vacancies')->where('id', $id)->value('slug');

        Carbon::setTestNow($close->copy()->subDay());
        $data = $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk()->json('data.company');

        self::assertSame(array_keys($data), ['company_id', 'name', 'logo_url', 'industry_id', 'city_geographic_area_id', 'mitra_kampus_active']);
        self::assertArrayNotHasKey('verification_status', $data, 'PD-2: no unsourced verification field.');
        self::assertArrayNotHasKey('verified', $data);
        self::assertArrayNotHasKey('is_verified', $data);
        self::assertNull($data['logo_url']);
    }

    // ---------------------------------------------------------------
    // External ATS
    // ---------------------------------------------------------------

    public function test_external_ats_state_disclosed_without_raw_url_or_side_effects(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('external@example.test');
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        $id = $this->publishedVacancy($recruiter, $company, $close, [
            'application_method' => 'EXTERNAL_ATS', 'external_ats_url' => 'https://careers.example.test/job/1',
        ]);
        $slug = DB::table('vacancies')->where('id', $id)->value('slug');
        $eventsBefore = DB::table('external_apply_events')->count();
        $applicationsBefore = DB::table('applications')->count();

        Carbon::setTestNow($close->copy()->subDay());
        $data = $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk()->json('data');

        self::assertSame('EXTERNAL_ATS', $data['application_method']);
        self::assertTrue($data['applies_externally']);
        self::assertArrayNotHasKey('external_ats_url', $data);
        self::assertSame($eventsBefore, DB::table('external_apply_events')->count(), 'A GET must never create an EXTERNAL_APPLY_STARTED event.');
        self::assertSame($applicationsBefore, DB::table('applications')->count(), 'A GET must never create an application.');
    }

    // ---------------------------------------------------------------
    // Reference data
    // ---------------------------------------------------------------

    public function test_reference_data_returns_only_active_bounded_categories(): void
    {
        $activeId = DB::table('industries')->insertGetId(['code' => 'IND-ACTIVE', 'name' => 'Aktif', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('industries')->insert(['code' => 'IND-INACTIVE', 'name' => 'Nonaktif', 'active' => false, 'created_at' => now(), 'updated_at' => now()]);

        $data = $this->getJson('/api/v1/public/reference-data')->assertOk()->json('data');

        foreach (['study_programs', 'industries', 'organization_types', 'geographic_areas', 'skills'] as $category) {
            self::assertArrayHasKey($category, $data);
        }
        $industryNames = array_column($data['industries'], 'name');
        self::assertContains('Aktif', $industryNames);
        self::assertNotContains('Nonaktif', $industryNames);
        self::assertStringNotContainsString('created_by', $this->getJson('/api/v1/public/reference-data')->getContent());
    }

    // ---------------------------------------------------------------
    // Rate limiting
    // ---------------------------------------------------------------

    public function test_public_endpoint_is_rate_limited_per_ip(): void
    {
        $limiter = app(\App\Domains\Shared\Support\PublicDiscoveryRateLimiter::class);
        // A fresh, unique IP per run — the Redis-backed limiter is not reset by
        // the database transaction rollback between tests.
        $ip = '203.0.113.'.random_int(10, 250);
        $request = \Illuminate\Http\Request::create('/api/v1/public/vacancies', 'GET', server: ['REMOTE_ADDR' => $ip]);

        for ($i = 0; $i < 120; $i++) {
            $result = $limiter->checkAndRecord($request);
            self::assertFalse($result['blocked']);
        }
        $blocked = $limiter->checkAndRecord($request);
        self::assertTrue($blocked['blocked']);
        self::assertGreaterThan(0, $blocked['retry_after']);
    }

    // ---------------------------------------------------------------
    // Query safety / N+1
    // ---------------------------------------------------------------

    public function test_listing_query_count_is_bounded_regardless_of_result_count(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('n1@example.test');
        $close = Carbon::parse('2026-12-20T09:00:00+00:00');
        for ($i = 0; $i < 8; $i++) {
            $this->publishedVacancy($recruiter, $company, $close, ['title' => "N1 Vacancy {$i}"]);
        }
        Carbon::setTestNow($close->copy()->subDay());

        // All fixture creation happens above, before logging starts — only the
        // two read requests below are measured.
        DB::enableQueryLog();
        $this->getJson('/api/v1/public/vacancies?per_page=3')->assertOk();
        $countFor3 = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->getJson('/api/v1/public/vacancies?per_page=8')->assertOk();
        $countFor8 = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertLessThanOrEqual($countFor3 + 2, $countFor8, 'Query count must not grow linearly with result-set size.');
    }

    // ---------------------------------------------------------------
    // Deferred scope — must NOT be present
    // ---------------------------------------------------------------

    public function test_deferred_scope_is_absent(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('deferred@example.test');
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
        $id = $this->publishedVacancy($recruiter, $company, $close, ['salary_min' => 5000000, 'salary_max' => 8000000]);
        $slug = DB::table('vacancies')->where('id', $id)->value('slug');

        Carbon::setTestNow($close->copy()->subDay());
        $data = $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk()->json('data');
        self::assertArrayNotHasKey('salary_min', $data);
        self::assertArrayNotHasKey('salary_max', $data);
        self::assertArrayNotHasKey('salary_currency', $data);
        $this->getJson('/api/v1/public/vacancies?salary_min=1')->assertStatus(422);

        // Only public/* routes are in scope here — authenticated Company
        // Onboarding's own `/companies/*` routes are a separate, frozen,
        // pre-existing surface and must not be flagged by this check.
        $publicUris = collect(Route::getRoutes())
            ->filter(static fn ($route): bool => str_starts_with($route->uri(), 'api/v1/public'))
            ->map(static fn ($route): string => $route->uri());
        self::assertFalse($publicUris->contains(fn (string $u): bool => str_contains($u, 'saved-vacanc')));
        // PD-2 adds the single-company endpoint; it must never become a
        // directory/listing route.
        self::assertFalse($publicUris->contains('api/v1/public/companies'));
        self::assertTrue($publicUris->contains('api/v1/public/companies/{slug}'));
        self::assertFalse($publicUris->contains(fn (string $u): bool => str_contains($u, 'sitemap')));
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    /** @return array{0: \App\Domains\Identity\Models\User, 1: \App\Domains\Company\Models\Company, 2: int, 3: string} */
    private function publicVacancy(string $email): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $close = Carbon::parse('2026-11-01T09:00:00+00:00');
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
