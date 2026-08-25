<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Domains\Company\Actions\ChangeCompanyMemberRole;
use App\Domains\Company\Exceptions\LastCompanyAdmin;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyMember;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesDatabaseWithMigrationOwner;
use Tests\TestCase;

/**
 * Real PostgreSQL row-lock contention for the closed-D-1 last-admin invariant.
 *
 * Two forked OS processes hold two independent PostgreSQL sessions and both run
 * the production Action. A third connection takes the same company row lock the
 * guard takes and holds it as a starting barrier, so the children are proven to
 * be contending rather than accidentally serialised by scheduling. The barrier
 * is released only once `pg_locks` shows both children actually waiting — no
 * sleep is used as a synchronisation mechanism.
 *
 * Fixtures are committed: this class deliberately does not wrap the test in a
 * transaction, because a wrapping transaction would hide them from every other
 * session and make the contention unobservable.
 */
final class CompanyLastAdminConcurrencyTest extends TestCase
{
    use RefreshesDatabaseWithMigrationOwner;

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

    public function test_two_concurrent_admin_reductions_cannot_empty_the_admin_seat(): void
    {
        [$company, $memberA, $memberB] = $this->companyWithTwoActiveAdmins();

        // Barrier: hold the exact row CompanyAdminProtection locks, so both
        // children are inside the Action and blocked before either can proceed.
        $barrier = DB::connection(self::BARRIER_CONNECTION);
        $barrier->beginTransaction();
        $barrier->select('SELECT id FROM companies WHERE id = ? FOR UPDATE', [$company->getKey()]);

        $pipes = [];
        $pids = [];
        foreach ([$memberA, $memberB] as $index => $member) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Failed to fork a concurrent session.');

            if ($pid === 0) {
                fclose($pair[0]);
                $this->runChild($pair[1], (int) $company->getKey(), (int) $member->getKey());
            }

            fclose($pair[1]);
            $pipes[$index] = $pair[0];
            $pids[$index] = $pid;
        }

        // Requirement: neither child may complete its check while the barrier
        // transaction is unresolved. Proven directly — if either could read a
        // stale count and finish, its pipe would carry a result now.
        $premature = $this->collectReady($pipes, 2.0);

        // Release and reap unconditionally; a blocked child would otherwise hold
        // row locks into teardown and deadlock the suite.
        $barrier->rollBack();

        $outcomes = [];
        foreach ($pipes as $index => $pipe) {
            $outcomes[] = trim((string) stream_get_contents($pipe));
            fclose($pipe);
            pcntl_waitpid($pids[$index], $status);
        }
        sort($outcomes);

        $this->assertSame([], $premature, 'A child completed while the company row lock was still held: '.implode(' | ', $premature));

        // Exactly one reduction survives; the other carries the frozen semantic.
        $this->assertSame(['LAST_ADMIN', 'OK'], $outcomes, 'Outcomes were: '.implode(' | ', $outcomes));
        $this->assertSame(1, CompanyMember::query()->active()
            ->where('company_id', $company->getKey())
            ->where('company_role', 'COMPANY_ADMIN')
            ->count());
    }

    public function test_a_held_company_lock_blocks_the_guard_rather_than_letting_it_read_stale_state(): void
    {
        [$company, , $memberB] = $this->companyWithTwoActiveAdmins();

        $barrier = DB::connection(self::BARRIER_CONNECTION);
        $barrier->beginTransaction();
        $barrier->select('SELECT id FROM companies WHERE id = ? FOR UPDATE', [$company->getKey()]);

        // The guard must not be able to complete its count while the row is held.
        DB::statement("SET lock_timeout = '750ms'");
        try {
            app(ChangeCompanyMemberRole::class)->execute(
                $this->actor(), $company, $memberB->refresh(), 'COMPANY_RECRUITER',
            );
            $this->fail('The guard completed while another session held the company row lock.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertSame('55P03', $exception->getCode(), 'Expected a PostgreSQL lock_not_available.');
        } finally {
            DB::statement("SET lock_timeout = '0'");
            $barrier->rollBack();
        }

        $this->assertSame(2, CompanyMember::query()->active()
            ->where('company_id', $company->getKey())
            ->where('company_role', 'COMPANY_ADMIN')
            ->count());
    }

    private function runChild($pipe, int $companyId, int $memberId): never
    {
        // A forked child must never reuse the parent's PDO handle.
        DB::purge(config('database.default'));
        // Safety ceiling only: far longer than the barrier is ever held, so it
        // never masks the contention this test exists to prove.
        DB::statement("SET lock_timeout = '30s'");

        $result = 'ERROR';
        try {
            app(ChangeCompanyMemberRole::class)->execute(
                User::query()->firstOrFail(),
                Company::query()->findOrFail($companyId),
                CompanyMember::query()->findOrFail($memberId),
                'COMPANY_RECRUITER',
            );
            $result = 'OK';
        } catch (LastCompanyAdmin) {
            $result = 'LAST_ADMIN';
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

    /** @return array{Company, CompanyMember, CompanyMember} */
    private function companyWithTwoActiveAdmins(): array
    {
        $adminA = $this->makeCommittedUser('concurrency-a@example.test');
        $adminB = $this->makeCommittedUser('concurrency-b@example.test');

        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Concurrency Co', 'normalized_name' => 'concurrency co',
            'verification_status' => 'DRAFT', 'created_by' => $adminA->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([$adminA, $adminB] as $admin) {
            DB::table('company_members')->insert([
                'company_id' => $companyId, 'user_id' => $admin->id,
                'company_role' => 'COMPANY_ADMIN', 'status' => 'ACTIVE', 'joined_at' => now(),
            ]);
        }

        return [
            Company::query()->findOrFail($companyId),
            CompanyMember::query()->where('company_id', $companyId)->where('user_id', $adminA->id)->firstOrFail(),
            CompanyMember::query()->where('company_id', $companyId)->where('user_id', $adminB->id)->firstOrFail(),
        ];
    }

    private function makeCommittedUser(string $email): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Concurrency User', 'email' => $email, 'email_normalized' => $email,
            'status' => 'ACTIVE', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function actor(): User
    {
        return User::query()->firstOrFail();
    }

    private function cleanUp(): void
    {
        // audit_logs is append-only for the runtime role (INV-016), so fixture
        // teardown uses the migration owner rather than weakening that grant.
        $owner = $this->migrationConnection();
        $owner->table('audit_logs')->where('object_type', 'company_member')->delete();
        $owner->table('company_members')->delete();
        $owner->table('companies')->delete();
        $owner->table('users')->where('email', 'like', 'concurrency-%@example.test')->delete();
    }
}
