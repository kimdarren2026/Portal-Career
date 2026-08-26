<?php

declare(strict_types=1);

namespace Tests\Feature\Application;

use App\Domains\Application\Actions\SubmitApplication;
use App\Domains\Application\Exceptions\ApplicationAlreadyExists;
use App\Domains\Application\Support\ApplicationConsentVersion;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Real PostgreSQL row-lock contention for the one-lifecycle invariant
 * (`uq_applications_candidate_vacancy`). Two forked OS processes hold two
 * independent PostgreSQL sessions and both run the production
 * `SubmitApplication` action for the SAME candidate against the SAME
 * vacancy. A third connection takes the same vacancy row lock the action
 * takes first and holds it as a starting barrier, so the children are proven
 * to be contending rather than accidentally serialised by scheduling. The
 * barrier is released only after both children are confirmed blocked — no
 * sleep is used as a synchronisation mechanism.
 *
 * This mirrors `CompanyLastAdminConcurrencyTest`'s proven pcntl/PostgreSQL
 * concurrency pattern. Fixtures are committed: this class deliberately does
 * not wrap the test in a transaction, because a wrapping transaction would
 * hide them from every other session and make the contention unobservable.
 */
final class ApplicationSubmitConcurrencyTest extends VacancyTestCase
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

    public function test_two_concurrent_submits_for_the_same_candidate_and_vacancy_never_double_apply(): void
    {
        [$vacancyId, $candidateUser, $profileId] = $this->publishedVacancyAndCandidate();

        // Barrier: hold the exact row SubmitApplication locks first, so both
        // children are inside the action and blocked before either can proceed.
        $barrier = DB::connection(self::BARRIER_CONNECTION);
        $barrier->beginTransaction();
        $barrier->select('SELECT id FROM vacancies WHERE id = ? FOR UPDATE', [$vacancyId]);

        $pipes = [];
        $pids = [];
        foreach ([0, 1] as $index) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Failed to fork a concurrent session.');

            if ($pid === 0) {
                fclose($pair[0]);
                $this->runChild($pair[1], $vacancyId, (int) $candidateUser->getKey(), $profileId);
            }

            fclose($pair[1]);
            $pipes[$index] = $pair[0];
            $pids[$index] = $pid;
        }

        // Requirement: neither child may complete its submit while the barrier
        // transaction is unresolved. Proven directly — if either could read
        // stale state and finish, its pipe would carry a result now.
        $premature = $this->collectReady($pipes, 2.0);

        // Release and reap unconditionally; a blocked child would otherwise
        // hold row locks into teardown and deadlock the suite.
        $barrier->rollBack();

        $outcomes = [];
        foreach ($pipes as $index => $pipe) {
            $outcomes[] = trim((string) stream_get_contents($pipe));
            fclose($pipe);
            pcntl_waitpid($pids[$index], $status);
        }
        sort($outcomes);

        $this->assertSame([], $premature, 'A child completed while the vacancy row lock was still held: '.implode(' | ', $premature));

        // Exactly one submit survives; the other carries the frozen
        // one-lifecycle contract — never a raw SQLSTATE/QueryException.
        $this->assertSame(['DUPLICATE', 'OK'], $outcomes, 'Outcomes were: '.implode(' | ', $outcomes));
        $this->assertSame(1, DB::table('applications')
            ->where('candidate_profile_id', $profileId)
            ->where('vacancy_id', $vacancyId)
            ->count());
        $this->assertSame(1, DB::table('application_status_histories')
            ->whereIn('application_id', DB::table('applications')->where('candidate_profile_id', $profileId)->where('vacancy_id', $vacancyId)->pluck('id'))
            ->count());
        $this->assertSame(1, DB::table('consents')
            ->whereIn('application_id', DB::table('applications')->where('candidate_profile_id', $profileId)->where('vacancy_id', $vacancyId)->pluck('id'))
            ->count());
    }

    private function runChild($pipe, int $vacancyId, int $candidateUserId, int $profileId): never
    {
        // A forked child must never reuse the parent's PDO handle.
        DB::purge(config('database.default'));
        // Safety ceiling only: far longer than the barrier is ever held, so it
        // never masks the contention this test exists to prove.
        DB::statement("SET lock_timeout = '30s'");

        $result = 'ERROR';
        try {
            app(SubmitApplication::class)->execute(
                User::query()->findOrFail($candidateUserId),
                CandidateProfile::query()->findOrFail($profileId),
                $vacancyId,
                [
                    'consent' => [
                        'consent_version' => ApplicationConsentVersion::CURRENT,
                        'consent_text_hash_reference' => 'sha256:concurrency-test',
                        'accepted' => true,
                    ],
                    'document_ids' => [],
                    'screening_answers' => [],
                ],
            );
            $result = 'OK';
        } catch (ApplicationAlreadyExists) {
            $result = 'DUPLICATE';
        } catch (\Throwable $exception) {
            $result = 'ERROR:'.$exception::class;
        }

        fwrite($pipe, $result);
        fflush($pipe);
        fclose($pipe);

        // SIGKILL rather than exit(): a clean shutdown would run the inherited
        // PDO destructors, and libpq would send a terminate for the *parent's*
        // barrier backend down the duplicated socket, killing that connection.
        posix_kill(posix_getpid(), SIGKILL);
        exit(0);
    }

    /**
     * Blocks for up to $seconds and returns whatever any child has already
     * written. An empty result means every child is still blocked.
     *
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

    /** @return array{0: int, 1: User, 2: int} */
    private function publishedVacancyAndCandidate(): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('concurrency-recruiter@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ]);
        $approver = $this->moderator('concurrency-approver@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$vacancyId}/approve")->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update([
            'current_status' => 'PUBLISHED', 'published_at' => $open,
        ]);
        Carbon::setTestNow($close->copy()->subDay());

        $candidateUser = $this->makeUser('concurrency-candidate@example.test', UserStatus::Active);
        $this->assignRole($candidateUser, RoleCode::CandidateExternal);
        $profileId = (int) DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id, 'current_candidate_type' => 'EXTERNAL', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$vacancyId, $candidateUser, $profileId];
    }

    private function cleanUp(): void
    {
        $owner = $this->migrationConnection();
        $emails = ['concurrency-recruiter@example.test', 'concurrency-approver@example.test', 'concurrency-candidate@example.test'];

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
