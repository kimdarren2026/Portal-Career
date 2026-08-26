<?php

declare(strict_types=1);

namespace Tests\Feature\Application;

use App\Domains\Application\Actions\TransitionApplication;
use App\Domains\Application\Enums\ApplicationStatus;
use App\Domains\Application\Exceptions\ApplicationInvalidTransition;
use App\Domains\Application\Models\Application;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Real PostgreSQL row-lock contention for `/transition`. Two forked OS
 * processes hold two independent PostgreSQL sessions and both run the
 * production `TransitionApplication` action for the SAME application,
 * attempting the SAME edge (`APPLIED -> UNDER_REVIEW`) simultaneously. A
 * third connection takes the same application row lock the action takes
 * first and holds it as a starting barrier, mirroring
 * `ApplicationSubmitConcurrencyTest`/`CompanyLastAdminConcurrencyTest`.
 *
 * The loser re-reads `current_status = UNDER_REVIEW` (the winner's committed
 * write) after the barrier releases and re-validates RA-1's edge from that
 * fresh state — `UNDER_REVIEW -> UNDER_REVIEW` is not a legal edge, so the
 * loser deterministically raises `ApplicationInvalidTransition`, never a raw
 * SQLSTATE, and writes no second history row.
 */
final class ApplicationTransitionConcurrencyTest extends VacancyTestCase
{
    private const BARRIER_CONNECTION = 'pgsql_barrier';

    /** Committed fixtures are required; the usual wrapping transaction is not applied. */
    public function beginDatabaseTransaction(): void {}

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required to drive two concurrent PostgreSQL sessions.');
        }

        config([
            'database.connections.'.self::BARRIER_CONNECTION => config('database.connections.'.config('database.default')),
        ]);
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->cleanUp();
        parent::tearDown();
    }

    public function test_two_concurrent_transitions_on_the_same_application_never_produce_a_double_history(): void
    {
        [$applicationId, $adminUser] = $this->applicationAtApplied();

        $barrier = DB::connection(self::BARRIER_CONNECTION);
        $barrier->beginTransaction();
        $barrier->select('SELECT id FROM applications WHERE id = ? FOR UPDATE', [$applicationId]);

        $pipes = [];
        $pids = [];
        foreach ([0, 1] as $index) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Failed to fork a concurrent session.');

            if ($pid === 0) {
                fclose($pair[0]);
                $this->runChild($pair[1], $applicationId, (int) $adminUser->getKey());
            }

            fclose($pair[1]);
            $pipes[$index] = $pair[0];
            $pids[$index] = $pid;
        }

        $premature = $this->collectReady($pipes, 2.0);

        $barrier->rollBack();

        $outcomes = [];
        foreach ($pipes as $index => $pipe) {
            $outcomes[] = trim((string) stream_get_contents($pipe));
            fclose($pipe);
            pcntl_waitpid($pids[$index], $status);
        }
        sort($outcomes);

        $this->assertSame([], $premature, 'A child completed while the application row lock was still held: '.implode(' | ', $premature));
        $this->assertSame(['INVALID_TRANSITION', 'OK'], $outcomes, 'Outcomes were: '.implode(' | ', $outcomes));

        $this->assertSame('UNDER_REVIEW', DB::table('applications')->where('id', $applicationId)->value('current_status'));
        $this->assertSame(2, DB::table('application_status_histories')->where('application_id', $applicationId)->count());
        $this->assertSame(1, DB::table('application_status_histories')->where('application_id', $applicationId)->where('event_type', 'STATUS_CHANGED')->count());
    }

    private function runChild($pipe, int $applicationId, int $adminUserId): never
    {
        // A forked child must never reuse the parent's PDO handle.
        DB::purge(config('database.default'));
        // Safety ceiling only: far longer than the barrier is ever held, so it
        // never masks the contention this test exists to prove.
        DB::statement("SET lock_timeout = '30s'");

        $result = 'ERROR';
        try {
            app(TransitionApplication::class)->execute(
                User::query()->findOrFail($adminUserId),
                Application::query()->findOrFail($applicationId),
                ApplicationStatus::UnderReview,
                'INTERNAL',
                null,
                null,
                null,
            );
            $result = 'OK';
        } catch (ApplicationInvalidTransition) {
            $result = 'INVALID_TRANSITION';
        } catch (\Throwable $exception) {
            $result = 'ERROR:'.$exception::class;
        }

        fwrite($pipe, $result);
        fflush($pipe);
        fclose($pipe);

        posix_kill(posix_getpid(), SIGKILL);
        exit(0);
    }

    /**
     * @param  array<int, resource>  $pipes
     * @return list<string>
     */
    private function collectReady(array $pipes, float $seconds): array
    {
        $read = $pipes;
        $write = null;
        $except = null;
        $ready = [];

        if (@stream_select($read, $write, $except, (int) $seconds, (int) (fmod($seconds, 1) * 1_000_000)) > 0) {
            foreach ($read as $pipe) {
                $ready[] = trim((string) fread($pipe, 256));
            }
        }

        return array_values(array_filter($ready, static fn (string $value): bool => $value !== ''));
    }

    /** @return array{0: int, 1: User} */
    private function applicationAtApplied(): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('transition-concurrency-recruiter@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ]);
        $approver = $this->moderator('transition-concurrency-approver@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$vacancyId}/approve")->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update([
            'current_status' => 'PUBLISHED', 'published_at' => $open,
        ]);
        Carbon::setTestNow($close->copy()->subDay());

        $candidateUser = $this->makeUser('transition-concurrency-candidate@example.test', UserStatus::Active);
        $this->assignRole($candidateUser, \App\Domains\Identity\Enums\RoleCode::CandidateExternal);
        $profileId = (int) DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id, 'current_candidate_type' => 'EXTERNAL', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $applicationId = (int) $this->actingAs($candidateUser)->postJson("/vacancies/{$vacancyId}/applications", [
            'consent' => [
                'consent_version' => \App\Domains\Application\Support\ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => 'sha256:'.str_repeat('a', 64),
                'accepted' => true,
            ],
        ])->assertCreated()->json('data.id');

        return [$applicationId, $recruiter];
    }

    private function cleanUp(): void
    {
        $owner = $this->migrationConnection();
        $emails = ['transition-concurrency-recruiter@example.test', 'transition-concurrency-approver@example.test', 'transition-concurrency-candidate@example.test'];

        $userIds = $owner->table('users')->whereIn('email', $emails)->pluck('id');
        $profileIds = $owner->table('candidate_profiles')->whereIn('user_id', $userIds)->pluck('id');
        $companyIds = $owner->table('company_members')->whereIn('user_id', $userIds)->pluck('company_id')->unique();
        $vacancyIds = $owner->table('vacancies')->whereIn('company_id', $companyIds)->pluck('id');
        $applicationIds = $owner->table('applications')->whereIn('vacancy_id', $vacancyIds)->orWhereIn('candidate_profile_id', $profileIds)->pluck('id');

        $owner->table('consents')->whereIn('application_id', $applicationIds)->delete();
        $owner->table('application_status_histories')->whereIn('application_id', $applicationIds)->delete();
        $owner->table('application_documents')->whereIn('application_id', $applicationIds)->delete();
        $owner->table('application_screening_answers')->whereIn('application_id', $applicationIds)->delete();
        $owner->table('audit_logs')->where('object_type', 'application')->whereIn('object_id', $applicationIds)->delete();
        $owner->table('notifications')->whereIn('user_id', $userIds)->delete();
        $owner->table('email_outbox')->where('related_object_type', 'application')->whereIn('related_object_id', $applicationIds)->delete();
        $owner->table('applications')->whereIn('id', $applicationIds)->delete();

        $owner->table('vacancy_moderation_reviews')->whereIn('vacancy_id', $vacancyIds)->delete();
        $owner->table('vacancy_versions')->whereIn('vacancy_id', $vacancyIds)->delete();
        $owner->table('audit_logs')->where('object_type', 'vacancy')->whereIn('object_id', $vacancyIds)->delete();
        $owner->table('email_outbox')->where('related_object_type', 'vacancy')->whereIn('related_object_id', $vacancyIds)->delete();
        $owner->table('vacancies')->whereIn('id', $vacancyIds)->delete();

        $owner->table('candidate_profiles')->whereIn('id', $profileIds)->delete();
        $owner->table('company_members')->whereIn('company_id', $companyIds)->delete();
        $owner->table('companies')->whereIn('id', $companyIds)->delete();
        $owner->table('user_roles')->whereIn('user_id', $userIds)->delete();
        $owner->table('users')->whereIn('id', $userIds)->delete();
    }
}
