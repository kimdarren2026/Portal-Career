<?php

declare(strict_types=1);

namespace Tests\Feature\Application;

use App\Domains\Application\Actions\MoveApplicationStage;
use App\Domains\Application\Exceptions\ApplicationStageAlreadyCurrent;
use App\Domains\Application\Exceptions\ApplicationStageTargetInactive;
use App\Domains\Application\Models\Application;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Real PostgreSQL row-lock contention for `/move-stage`. Two forked OS
 * processes hold two independent PostgreSQL sessions and both run the
 * production `MoveApplicationStage` action for the SAME application,
 * targeting the SAME destination stage simultaneously — mirroring
 * `ApplicationTransitionConcurrencyTest`'s "same edge, twice" design. A
 * third connection takes the application row lock first and holds it as a
 * starting barrier.
 *
 * The loser re-reads `current_stage_id` (the winner's committed write) after
 * the barrier releases and rechecks MS-3 from that fresh state — the target
 * now equals `current_stage_id`, so the loser deterministically raises
 * `ApplicationStageAlreadyCurrent`, never a raw SQLSTATE, and writes no
 * second history row.
 *
 * A second test proves the stage-lock post-lock recheck (concurrency item
 * #36): a barrier holds the TARGET STAGE row locked while it is concurrently
 * disabled and committed; the blocked mover must reject the now-inactive
 * target once its own lock is granted, never admitting movement into a
 * stage that went inactive mid-flight.
 */
final class ApplicationStageMovementConcurrencyTest extends VacancyTestCase
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

    public function test_two_concurrent_moves_to_the_same_target_never_produce_a_double_history(): void
    {
        [$applicationId, $stageA, $stageB, $adminUser] = $this->applicationAtStage();

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
                $this->runMoveChild($pair[1], $applicationId, $stageB, (int) $adminUser->getKey());
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
        $this->assertSame(['ALREADY_CURRENT', 'OK'], $outcomes, 'Outcomes were: '.implode(' | ', $outcomes));

        $this->assertSame($stageB, (int) DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
        $this->assertSame(1, DB::table('application_status_histories')->where('application_id', $applicationId)->where('event_type', 'STAGE_CHANGED')->count());
    }

    public function test_target_stage_disabled_mid_flight_is_rejected_not_admitted(): void
    {
        [$applicationId, $stageA, $stageB, $adminUser] = $this->applicationAtStage();

        $barrier = DB::connection(self::BARRIER_CONNECTION);
        $barrier->beginTransaction();
        $barrier->select('SELECT id FROM recruitment_stages WHERE id = ? FOR UPDATE', [$stageB]);

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid, 'Failed to fork a concurrent session.');

        if ($pid === 0) {
            fclose($pair[0]);
            $this->runMoveChild($pair[1], $applicationId, $stageB, (int) $adminUser->getKey());
        }
        fclose($pair[1]);

        $premature = $this->collectReady([$pair[0]], 1.5);

        // While the mover is blocked on the stage lock, disable it THROUGH
        // THE SAME barrier transaction that holds the lock — a separate
        // connection's UPDATE would itself block behind that lock — then
        // commit, atomically releasing the lock and publishing the change.
        $barrier->update('UPDATE recruitment_stages SET active = false WHERE id = ?', [$stageB]);
        $barrier->commit();

        $outcome = trim((string) stream_get_contents($pair[0]));
        fclose($pair[0]);
        pcntl_waitpid($pid, $status);

        $this->assertSame([], $premature, 'The mover completed while the target stage lock was still held.');
        $this->assertSame('INACTIVE', $outcome);
        $this->assertSame($stageA, (int) DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
        $this->assertSame(0, DB::table('application_status_histories')->where('application_id', $applicationId)->where('event_type', 'STAGE_CHANGED')->count());
    }

    private function runMoveChild($pipe, int $applicationId, int $toStageId, int $adminUserId): never
    {
        // A forked child must never reuse the parent's PDO handle.
        DB::purge(config('database.default'));
        // Safety ceiling only: far longer than the barrier is ever held, so it
        // never masks the contention this test exists to prove.
        DB::statement("SET lock_timeout = '30s'");

        $result = 'ERROR';
        try {
            app(MoveApplicationStage::class)->execute(
                User::query()->findOrFail($adminUserId),
                Application::query()->findOrFail($applicationId),
                $toStageId,
                'INTERNAL',
                null,
            );
            $result = 'OK';
        } catch (ApplicationStageAlreadyCurrent) {
            $result = 'ALREADY_CURRENT';
        } catch (ApplicationStageTargetInactive) {
            $result = 'INACTIVE';
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

    /** @return array{0: int, 1: int, 2: int, 3: User} */
    private function applicationAtStage(): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('move-concurrency-recruiter@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ]);
        $approver = $this->moderator('move-concurrency-approver@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$vacancyId}/approve")->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update([
            'current_status' => 'PUBLISHED', 'published_at' => $open,
        ]);
        Carbon::setTestNow($close->copy()->subDay());

        $stageA = (int) $this->actingAs($recruiter)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'A', 'stage_type' => 'GENERAL', 'sort_order' => 0, 'active' => true,
        ])->assertCreated()->json('data.id');
        $stageB = (int) $this->actingAs($recruiter)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'B', 'stage_type' => 'GENERAL', 'sort_order' => 1, 'active' => true,
        ])->assertCreated()->json('data.id');

        $candidateUser = $this->makeUser('move-concurrency-candidate@example.test', UserStatus::Active);
        $this->assignRole($candidateUser, \App\Domains\Identity\Enums\RoleCode::CandidateExternal);
        DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id, 'current_candidate_type' => 'EXTERNAL', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $applicationId = (int) $this->actingAs($candidateUser)->postJson("/vacancies/{$vacancyId}/applications", [
            'consent' => [
                'consent_version' => \App\Domains\Application\Support\ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => 'sha256:'.str_repeat('a', 64),
                'accepted' => true,
            ],
        ])->assertCreated()->json('data.id');

        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stageA]);

        return [$applicationId, $stageA, $stageB, $recruiter];
    }

    private function cleanUp(): void
    {
        $owner = $this->migrationConnection();
        $emails = ['move-concurrency-recruiter@example.test', 'move-concurrency-approver@example.test', 'move-concurrency-candidate@example.test'];

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

        $owner->table('recruitment_stages')->whereIn('vacancy_id', $vacancyIds)->delete();
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
