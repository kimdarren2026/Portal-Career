<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Evaluation\Actions\SubmitEvaluation;
use App\Domains\Recruitment\Evaluation\Exceptions\EvaluationAlreadySubmitted;
use App\Domains\Recruitment\Evaluation\Models\Evaluation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Real PostgreSQL row-lock contention for Evaluation / Scoring
 * Foundation v1's double-submit race, mirroring the established
 * `pcntl_fork()` + `pgsql_barrier` + `FOR UPDATE` pattern already proven for
 * Selection Schedule and Application Stage Movement.
 *
 * Two forked OS processes hold two independent PostgreSQL sessions and both
 * run the production `SubmitEvaluation` action against the SAME draft
 * evaluation simultaneously. A third connection takes the same evaluation
 * row lock the action takes first and holds it as a starting barrier.
 */
final class EvaluationConcurrencyTest extends VacancyTestCase
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

    public function test_two_concurrent_submits_of_the_same_draft_one_succeeds_one_already_submitted(): void
    {
        [$evaluationId, $admin] = $this->fixture();

        $barrier = DB::connection(self::BARRIER_CONNECTION);
        $barrier->beginTransaction();
        $barrier->select('SELECT id FROM evaluations WHERE id = ? FOR UPDATE', [$evaluationId]);

        $pipes = [];
        $pids = [];
        foreach ([0, 1] as $index) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Failed to fork a concurrent session.');

            if ($pid === 0) {
                fclose($pair[0]);
                $this->runSubmitChild($pair[1], $evaluationId, (int) $admin->getKey());
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

        $this->assertSame([], $premature, 'A child completed while the evaluation row lock was still held: '.implode(' | ', $premature));
        $this->assertSame(['ALREADY_SUBMITTED', 'OK'], $outcomes, 'Outcomes were: '.implode(' | ', $outcomes));

        $this->assertNotNull(DB::table('evaluations')->where('id', $evaluationId)->value('submitted_at'));
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'evaluation_submitted')->where('object_id', $evaluationId)->count());
        $this->assertSame(1, DB::table('notifications')->where('related_object_type', 'evaluation')->where('related_object_id', $evaluationId)
            ->where('body_reference', 'evaluation.submitted.owner')->count());
    }

    private function runSubmitChild($pipe, int $evaluationId, int $adminUserId): never
    {
        DB::purge(config('database.default'));
        DB::statement("SET lock_timeout = '30s'");

        $result = 'ERROR';
        try {
            app(SubmitEvaluation::class)->execute(
                User::query()->findOrFail($adminUserId),
                Evaluation::query()->findOrFail($evaluationId),
            );
            $result = 'OK';
        } catch (EvaluationAlreadySubmitted) {
            $result = 'ALREADY_SUBMITTED';
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
    private function fixture(): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('evaluation-concurrency-recruiter@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ]);
        $approver = $this->moderator('evaluation-concurrency-approver@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$vacancyId}/approve")->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update([
            'current_status' => 'PUBLISHED', 'published_at' => $open,
        ]);
        Carbon::setTestNow($close->copy()->subDays(10));

        $stageId = (int) $this->actingAs($recruiter)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'A', 'stage_type' => 'GENERAL', 'sort_order' => 0, 'active' => true,
        ])->assertCreated()->json('data.id');

        $candidateUser = $this->makeUser('evaluation-concurrency-candidate@example.test', UserStatus::Active);
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

        $evaluationId = (int) $this->actingAs($recruiter)->postJson("/applications/{$applicationId}/evaluations", [
            'recruitment_stage_id' => $stageId,
            'recommendation' => 'HIRE',
            'comments' => 'Strong candidate.',
        ])->assertCreated()->json('data.id');

        return [$evaluationId, $recruiter];
    }

    private function cleanUp(): void
    {
        $owner = $this->migrationConnection();
        $emails = ['evaluation-concurrency-recruiter@example.test', 'evaluation-concurrency-approver@example.test', 'evaluation-concurrency-candidate@example.test'];

        $userIds = $owner->table('users')->whereIn('email', $emails)->pluck('id');
        $profileIds = $owner->table('candidate_profiles')->whereIn('user_id', $userIds)->pluck('id');
        $companyIds = $owner->table('company_members')->whereIn('user_id', $userIds)->pluck('company_id')->unique();
        $vacancyIds = $owner->table('vacancies')->whereIn('company_id', $companyIds)->pluck('id');
        $applicationIds = $owner->table('applications')->whereIn('vacancy_id', $vacancyIds)->orWhereIn('candidate_profile_id', $profileIds)->pluck('id');
        $evaluationIds = $owner->table('evaluations')->whereIn('application_id', $applicationIds)->pluck('id');

        $owner->table('evaluation_items')->whereIn('evaluation_id', $evaluationIds)->delete();
        $owner->table('email_outbox')->where('related_object_type', 'evaluation')->whereIn('related_object_id', $evaluationIds)->delete();
        $owner->table('notifications')->whereIn('user_id', $userIds)->delete();
        $owner->table('audit_logs')->where('object_type', 'evaluation')->whereIn('object_id', $evaluationIds)->delete();
        $owner->table('evaluations')->whereIn('id', $evaluationIds)->delete();

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
