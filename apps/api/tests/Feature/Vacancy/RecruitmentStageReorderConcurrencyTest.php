<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Actions\ReorderRecruitmentStages;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * Real PostgreSQL row-lock contention for `POST /vacancies/{vacancy}/stages/reorder`.
 * Two forked OS processes hold two independent PostgreSQL sessions and both
 * run the production `ReorderRecruitmentStages` action against the SAME
 * vacancy's stage set, each submitting a different full permutation of the
 * same three stage ids. A third connection takes the same vacancy row lock
 * the action takes first and holds it as a starting barrier, mirroring
 * `ApplicationTransitionConcurrencyTest`/`CompanyLastAdminConcurrencyTest`.
 *
 * `recruitment_stages` has no unique constraint on `(vacancy_id, sort_order)`
 * — the invariant under test is that "Reorder locks the vacancy's stage set"
 * (API_CONTRACT.md) genuinely serialises two concurrent whole-set writes so
 * the final state is exactly one coherent permutation, never an interleaved
 * mix of both requests' orderings.
 */
final class RecruitmentStageReorderConcurrencyTest extends VacancyTestCase
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
        $this->cleanUp();
        parent::tearDown();
    }

    public function test_two_concurrent_reorders_never_interleave_and_leave_one_coherent_permutation(): void
    {
        [$vacancyId, $stageIds, $adminUserId] = $this->vacancyWithThreeStages();
        [$idA, $idB, $idC] = $stageIds;
        $orderingOne = [$idC, $idA, $idB];
        $orderingTwo = [$idB, $idC, $idA];

        $barrier = DB::connection(self::BARRIER_CONNECTION);
        $barrier->beginTransaction();
        $barrier->select('SELECT id FROM vacancies WHERE id = ? FOR UPDATE', [$vacancyId]);

        $pipes = [];
        $pids = [];
        foreach ([$orderingOne, $orderingTwo] as $index => $ordering) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Failed to fork a concurrent session.');

            if ($pid === 0) {
                fclose($pair[0]);
                $this->runChild($pair[1], $vacancyId, $adminUserId, $ordering);
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

        $this->assertSame([], $premature, 'A child completed while the vacancy row lock was still held: '.implode(' | ', $premature));
        $this->assertSame(['OK', 'OK'], $outcomes, 'Both valid whole-set reorders should succeed once serialised: '.implode(' | ', $outcomes));

        // The final state must be exactly one of the two submitted
        // permutations, in full, never a mix of both.
        $final = DB::table('recruitment_stages')->where('vacancy_id', $vacancyId)
            ->orderBy('sort_order')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $this->assertTrue($final === $orderingOne || $final === $orderingTwo, 'Final order was neither submitted permutation: '.implode(',', $final));
    }

    private function runChild($pipe, int $vacancyId, int $adminUserId, array $ordering): never
    {
        // A forked child must never reuse the parent's PDO handle.
        DB::purge(config('database.default'));
        DB::statement("SET lock_timeout = '30s'");

        $result = 'ERROR';
        try {
            app(ReorderRecruitmentStages::class)->execute(
                User::query()->findOrFail($adminUserId),
                Vacancy::query()->findOrFail($vacancyId),
                $ordering,
            );
            $result = 'OK';
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

    /** @return array{0: int, 1: list<int>, 2: int} */
    private function vacancyWithThreeStages(): array
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-reorder-concurrency@example.test');
        $vacancyId = $this->createVacancy($admin, $company);

        $ids = [];
        foreach (['A', 'B', 'C'] as $i => $name) {
            $ids[] = (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
                'name' => $name, 'stage_type' => 'GENERAL', 'sort_order' => $i, 'active' => true,
            ])->assertCreated()->json('data.id');
        }

        return [$vacancyId, $ids, (int) $admin->getKey()];
    }

    private function cleanUp(): void
    {
        $owner = $this->migrationConnection();
        $emails = ['stage-reorder-concurrency@example.test'];

        $userIds = $owner->table('users')->whereIn('email', $emails)->pluck('id');
        $companyIds = $owner->table('company_members')->whereIn('user_id', $userIds)->pluck('company_id')->unique();
        $vacancyIds = $owner->table('vacancies')->whereIn('company_id', $companyIds)->pluck('id');

        $owner->table('recruitment_stages')->whereIn('vacancy_id', $vacancyIds)->delete();
        $owner->table('vacancy_moderation_reviews')->whereIn('vacancy_id', $vacancyIds)->delete();
        $owner->table('vacancy_versions')->whereIn('vacancy_id', $vacancyIds)->delete();
        $owner->table('audit_logs')->where('object_type', 'vacancy')->whereIn('object_id', $vacancyIds)->delete();
        $owner->table('email_outbox')->where('related_object_type', 'vacancy')->whereIn('related_object_id', $vacancyIds)->delete();
        $owner->table('vacancies')->whereIn('id', $vacancyIds)->delete();

        $owner->table('company_members')->whereIn('company_id', $companyIds)->delete();
        $owner->table('companies')->whereIn('id', $companyIds)->delete();
        $owner->table('user_roles')->whereIn('user_id', $userIds)->delete();
        $owner->table('users')->whereIn('id', $userIds)->delete();
    }
}
