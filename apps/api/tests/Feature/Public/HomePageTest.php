<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * PGC-V1 / PD-G — the public `/` homepage. Real data only, no metrics, no
 * environment diagnostics.
 */
final class HomePageTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_homepage_renders_with_a_truthful_empty_state_when_there_are_no_vacancies(): void
    {
        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('public/Home')->where('latest_vacancies', [])
        );
    }

    public function test_homepage_shows_only_real_published_vacancies_capped_at_six(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('home-real@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(10);
        Carbon::setTestNow($close->copy()->subDay());

        $publishedSlugs = [];
        for ($i = 0; $i < 8; $i++) {
            $id = $this->vacancyAt($recruiter, $company, 'PUBLISHED', [
                'title' => "Nyata {$i}",
                'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
            ]);
            DB::table('vacancies')->where('id', $id)->update(['published_at' => $open->copy()->addMinutes($i)]);
            $publishedSlugs[] = DB::table('vacancies')->where('id', $id)->value('slug');
        }
        // A DRAFT vacancy must never appear.
        $this->vacancyAt($recruiter, $company, 'DRAFT', ['title' => 'Rahasia Draf']);

        $this->get('/')->assertOk()->assertInertia(function (Assert $page): void {
            $page->component('public/Home')->has('latest_vacancies', 6);
            $slugs = collect($page->toArray()['props']['latest_vacancies'])->pluck('slug');
            self::assertFalse($slugs->contains(fn ($s) => str_contains((string) $s, 'rahasia-draf')));
        });
    }

    public function test_root_no_longer_leaks_environment_diagnostics(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee(app()->version(), escape: false);
        $response->assertDontSee(app()->environment(), escape: false);
        $response->assertDontSee('"php":', escape: false);
    }

    public function test_health_endpoints_still_respond_off_the_public_root(): void
    {
        $this->get('/up')->assertOk();
        $this->get('/health/live')->assertOk();
    }
}
