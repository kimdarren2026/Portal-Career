<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Vacancy\Actions\PublishScheduledVacancies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * B-4 — a company vacancy publishes only through approval inside its active
 * window or through the scheduler. No actor of any role can publish directly.
 */
final class VacancyPublicationTest extends VacancyTestCase
{
    public function test_a_scheduled_vacancy_is_not_published_before_open_at(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('pub-early@example.test');
        $id = $this->scheduled($recruiter, $company, now()->addDays(2));

        self::assertSame(0, app(PublishScheduledVacancies::class)->execute());

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('SCHEDULED', $row->current_status);
        self::assertNull($row->published_at);
        self::assertSame(0, $this->auditCount('vacancy_published', $id));
    }

    public function test_the_scheduler_publishes_once_open_at_is_reached(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('pub-due@example.test');
        $id = $this->scheduled($recruiter, $company, now()->subMinute());

        self::assertSame(1, app(PublishScheduledVacancies::class)->execute());

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('PUBLISHED', $row->current_status);
        self::assertNotNull($row->published_at);
        self::assertSame(1, $this->auditCount('vacancy_published', $id));
        self::assertGreaterThan(0, $this->outboxCount($id));
    }

    public function test_a_repeated_scheduler_run_neither_republishes_nor_moves_published_at(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('pub-repeat@example.test');
        $id = $this->scheduled($recruiter, $company, now()->subMinute());

        app(PublishScheduledVacancies::class)->execute();
        $first = DB::table('vacancies')->where('id', $id)->value('published_at');

        $this->travel(10)->minutes();
        self::assertSame(0, app(PublishScheduledVacancies::class)->execute());

        self::assertSame($first, DB::table('vacancies')->where('id', $id)->value('published_at'));
        self::assertSame(1, $this->auditCount('vacancy_published', $id), 'INV-013: published exactly once.');
    }

    public function test_the_scheduler_touches_no_other_status(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('pub-scope@example.test');
        $ids = [];
        foreach (['APPROVED', 'PUBLISHED', 'SUSPENDED', 'CLOSED', 'EXPIRED', 'PENDING_REVIEW', 'DRAFT'] as $status) {
            $id = $this->vacancyAt($recruiter, $company, $status, [
                'open_at' => now()->subDay()->toIso8601String(),
                'close_at' => now()->addDays(9)->toIso8601String(),
            ]);
            $ids[$status] = $id;
        }

        self::assertSame(0, app(PublishScheduledVacancies::class)->execute());

        foreach ($ids as $status => $id) {
            self::assertSame($status, DB::table('vacancies')->where('id', $id)->value('current_status'));
        }
    }

    public function test_no_publish_route_exists_for_any_actor(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('pub-route@example.test');
        $id = $this->scheduled($recruiter, $company, now()->addDay());

        self::assertNull(Route::getRoutes()->getByName('vacancies.publish'));
        $registered = collect(Route::getRoutes())->map(
            static fn ($route): string => strtoupper(implode('|', $route->methods())).' '.$route->uri(),
        );
        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'publish')));

        // Owner, Career Center and Super Admin all reach nothing.
        foreach ([
            $recruiter,
            $this->moderator('pub-route-cc@example.test'),
            $this->moderator('pub-route-sa@example.test', RoleCode::SuperAdmin),
        ] as $actor) {
            $this->actingAs($actor)->postJson("/vacancies/{$id}/publish")->assertNotFound();
        }

        self::assertSame('SCHEDULED', DB::table('vacancies')->where('id', $id)->value('current_status'));
    }

    public function test_no_auto_expire_job_or_event_exists(): void
    {
        // O-7 is open: nothing may expire a published vacancy automatically and
        // no vacancy_expired audit event may exist.
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('pub-expiry@example.test');
        $id = $this->vacancyAt($recruiter, $company, 'PUBLISHED', [
            'open_at' => now()->subDays(30)->toIso8601String(),
            'close_at' => now()->subDay()->toIso8601String(),
        ]);

        app(PublishScheduledVacancies::class)->execute();
        $this->artisan('vacancies:publish-scheduled')->assertSuccessful();

        self::assertSame('PUBLISHED', DB::table('vacancies')->where('id', $id)->value('current_status'));
        self::assertSame(0, DB::table('audit_logs')->where('action', 'vacancy_expired')->count());
        self::assertSame(0, DB::table('vacancies')->where('current_status', 'EXPIRED')->count());

        $commands = array_keys(app(\Illuminate\Contracts\Console\Kernel::class)->all());
        self::assertNotContains('vacancies:expire', $commands);
    }

    public function test_no_public_vacancy_discovery_route_exists(): void
    {
        $registered = collect(Route::getRoutes())->map(static fn ($route): string => $route->uri());
        foreach (['public/vacancies', 'public/vacancies/{slug}'] as $absent) {
            self::assertFalse($registered->contains($absent), "Public discovery is a later phase: {$absent}");
        }
    }

    private function scheduled($recruiter, $company, $openAt): int
    {
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => $openAt->toIso8601String(),
            'close_at' => now()->addDays(60)->toIso8601String(),
        ]);
        DB::table('vacancies')->where('id', $id)->update(['current_status' => 'SCHEDULED']);

        return $id;
    }
}
