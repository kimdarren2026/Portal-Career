<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\Application\Models\Application;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Outcome\Actions\CreateRecruitmentOutcome;
use App\Domains\Recruitment\Outcome\Exceptions\OutcomeAlreadyRecorded;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Real PostgreSQL row-lock contention for Recruitment Outcome Foundation v1,
 * mirroring `OfferingConcurrencyTest`'s established pattern: `pcntl_fork()`,
 * a dedicated `pgsql_barrier` connection holding a real `FOR UPDATE` lock,
 * and `stream_select()` IPC — never a sequential simulation.
 *
 * Two processes race to record an outcome for the SAME `INTERNAL_APPLICATION`
 * with different (irrelevant to the Action, which takes no Idempotency-Key
 * itself) inputs. The application row lock (`CreateRecruitmentOutcome`'s own
 * first lock) serializes them — exactly one succeeds, the loser observes the
 * winner's committed row and raises `OutcomeAlreadyRecorded`. The database's
 * partial unique index (`uq_recruitment_outcomes_application`) is the final
 * backstop; this test proves the application-level lock already prevents the
 * race before that index would ever be needed.
 */
final class RecruitmentOutcomeConcurrencyTest extends VacancyTestCase
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

    public function test_two_concurrent_creates_for_same_application_one_succeeds_one_gets_already_recorded(): void
    {
        [$admin, $applicationId] = $this->fixture();

        $barrier = DB::connection(self::BARRIER_CONNECTION);
        $barrier->beginTransaction();
        $barrier->select('SELECT id FROM applications WHERE id = ? FOR UPDATE', [$applicationId]);

        $pipes = [];
        $pids = [];
        foreach (['HIRED', 'REJECTED'] as $index => $outcomeValue) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Failed to fork a concurrent session.');

            if ($pid === 0) {
                fclose($pair[0]);
                $this->runCreateChild($pair[1], (int) $admin->getKey(), $applicationId, $outcomeValue);
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
        $this->assertSame(['ALREADY_RECORDED', 'OK'], $outcomes, 'Outcomes were: '.implode(' | ', $outcomes));

        $this->assertSame(1, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'recruitment_outcome_recorded')
            ->whereRaw("(change_summary->>'application_id')::bigint = ?", [$applicationId])->count());
    }

    private function runCreateChild($pipe, int $actorId, int $applicationId, string $outcomeValue): never
    {
        DB::purge(config('database.default'));
        DB::statement("SET lock_timeout = '30s'");

        $result = 'ERROR';
        try {
            app(CreateRecruitmentOutcome::class)->execute(
                User::query()->findOrFail($actorId),
                Application::query()->findOrFail($applicationId),
                ['outcome' => $outcomeValue, 'reported_by_source' => 'COMPANY', 'notes' => null],
            );
            $result = 'OK';
        } catch (OutcomeAlreadyRecorded) {
            $result = 'ALREADY_RECORDED';
        } catch (\Throwable $exception) {
            $result = 'ERROR:'.$exception::class.':'.$exception->getMessage();
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

    /** @return array{0: User, 1: int} */
    private function fixture(): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('outcome-concurrency-recruiter@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ]);
        $approver = $this->moderator('outcome-concurrency-approver@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$vacancyId}/approve")->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update([
            'current_status' => 'PUBLISHED', 'published_at' => $open,
        ]);
        Carbon::setTestNow($close->copy()->subDays(10));

        $candidateUser = $this->makeUser('outcome-concurrency-candidate@example.test', UserStatus::Active);
        $this->assignRole($candidateUser, RoleCode::CandidateExternal);
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

        return [$recruiter, $applicationId];
    }

    private function cleanUp(): void
    {
        $owner = $this->migrationConnection();
        $emails = [
            'outcome-concurrency-recruiter@example.test',
            'outcome-concurrency-approver@example.test',
            'outcome-concurrency-candidate@example.test',
        ];

        $userIds = $owner->table('users')->whereIn('email', $emails)->pluck('id');
        $profileIds = $owner->table('candidate_profiles')->whereIn('user_id', $userIds)->pluck('id');
        $companyIds = $owner->table('company_members')->whereIn('user_id', $userIds)->pluck('company_id')->unique();
        $vacancyIds = $owner->table('vacancies')->whereIn('company_id', $companyIds)->pluck('id');
        $applicationIds = $owner->table('applications')->whereIn('vacancy_id', $vacancyIds)->orWhereIn('candidate_profile_id', $profileIds)->pluck('id');

        $owner->table('audit_logs')->where('object_type', 'recruitment_outcome')
            ->whereIn('object_id', $owner->table('recruitment_outcomes')->whereIn('application_id', $applicationIds)->pluck('id'))->delete();
        $owner->table('recruitment_outcomes')->whereIn('application_id', $applicationIds)->delete();
        $owner->table('notifications')->whereIn('user_id', $userIds)->delete();

        $owner->table('consents')->whereIn('application_id', $applicationIds)->delete();
        $owner->table('application_status_histories')->whereIn('application_id', $applicationIds)->delete();
        $owner->table('application_documents')->whereIn('application_id', $applicationIds)->delete();
        $owner->table('application_screening_answers')->whereIn('application_id', $applicationIds)->delete();
        $owner->table('audit_logs')->where('object_type', 'application')->whereIn('object_id', $applicationIds)->delete();
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
