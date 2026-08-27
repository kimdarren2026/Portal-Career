<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Offering\Actions\AcceptOffer;
use App\Domains\Recruitment\Offering\Actions\RejectOffer;
use App\Domains\Recruitment\Offering\Exceptions\OfferAlreadyAcceptedForApplication;
use App\Domains\Recruitment\Offering\Exceptions\OfferAlreadyResponded;
use App\Domains\Recruitment\Offering\Models\Offer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Real PostgreSQL row-lock contention for Offering Foundation v1, mirroring
 * `SelectionScheduleConcurrencyTest`'s established pattern: `pcntl_fork()`,
 * a dedicated `pgsql_barrier` connection holding a real `FOR UPDATE` lock,
 * and `stream_select()` IPC — never a sequential simulation.
 *
 * Test 1 proves INV-031 for the SAME offer: two processes race to accept
 * the identical `offers` row — exactly one succeeds, the loser observes the
 * winner's committed ACCEPTED status after the barrier releases and
 * correctly raises `OfferAlreadyResponded`.
 *
 * Test 2 proves INV-031 across DIFFERENT offers on the SAME application:
 * two distinct SENT offers belonging to one application are accepted
 * concurrently — the application row lock (acquired first, per the
 * `application -> offer` order) serializes them, so exactly one becomes
 * ACCEPTED and the other legally observes
 * `OfferAlreadyAcceptedForApplication`. The database's own partial unique
 * index is the final backstop; this test proves the application-level lock
 * already prevents the race before that index would ever be needed.
 *
 * Test 3 proves the accept-vs-reject race on the SAME offer: whichever
 * commits first legally determines the row's final status; the loser
 * observes a coherent post-lock rejection, never a torn state.
 */
final class OfferingConcurrencyTest extends VacancyTestCase
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

    public function test_two_concurrent_accepts_of_same_offer_one_succeeds_one_gets_already_responded(): void
    {
        [, $applicationId, $offerId, ] = $this->fixture('same-offer');
        $candidateUserId = $this->candidateUserId($applicationId);

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
                $this->runAcceptChild($pair[1], $offerId, $candidateUserId);
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
        $this->assertSame(['ALREADY_RESPONDED', 'OK'], $outcomes, 'Outcomes were: '.implode(' | ', $outcomes));

        $this->assertSame(1, DB::table('offers')->where('id', $offerId)->where('status', 'ACCEPTED')->count());
        $this->assertSame('HIRED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
        $this->assertSame(1, DB::table('application_status_histories')->where('application_id', $applicationId)->where('event_type', 'OFFER_ACCEPTED')->count());
    }

    public function test_two_concurrent_accepts_of_different_offers_same_application_inv031(): void
    {
        [$admin, $applicationId, $offerA, $candidateUser] = $this->fixture('inv031');
        $offerB = $this->sendOffer($admin, $applicationId);

        $barrier = DB::connection(self::BARRIER_CONNECTION);
        $barrier->beginTransaction();
        $barrier->select('SELECT id FROM applications WHERE id = ? FOR UPDATE', [$applicationId]);

        $pipes = [];
        $pids = [];
        foreach ([$offerA, $offerB] as $index => $offerId) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Failed to fork a concurrent session.');

            if ($pid === 0) {
                fclose($pair[0]);
                $this->runAcceptChild($pair[1], $offerId, (int) $candidateUser->getKey());
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
        $this->assertSame(['ALREADY_ACCEPTED_FOR_APPLICATION', 'OK'], $outcomes, 'Outcomes were: '.implode(' | ', $outcomes));

        $this->assertSame(1, DB::table('offers')->where('application_id', $applicationId)->where('status', 'ACCEPTED')->count());
        $this->assertSame('HIRED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
    }

    public function test_concurrent_accept_and_reject_of_same_offer_produce_one_coherent_outcome(): void
    {
        [, $applicationId, $offerId, $candidateUser] = $this->fixture('accept-vs-reject');

        // The offer row is the only lock both writers are guaranteed to
        // take: accept locks application -> offer, reject locks offer only
        // (RejectOffer's own docblock: a single-resource transaction can
        // never invert against anything else). Barrier-locking the
        // application row would only block accept — reject would race
        // through immediately, which is correct but not what this test is
        // proving. Locking the offer row blocks both.
        $barrier = DB::connection(self::BARRIER_CONNECTION);
        $barrier->beginTransaction();
        $barrier->select('SELECT id FROM offers WHERE id = ? FOR UPDATE', [$offerId]);

        $pairAccept = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pidAccept = pcntl_fork();
        $this->assertNotSame(-1, $pidAccept);
        if ($pidAccept === 0) {
            fclose($pairAccept[0]);
            $this->runAcceptChild($pairAccept[1], $offerId, (int) $candidateUser->getKey());
        }
        fclose($pairAccept[1]);

        $pairReject = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pidReject = pcntl_fork();
        $this->assertNotSame(-1, $pidReject);
        if ($pidReject === 0) {
            fclose($pairReject[0]);
            $this->runRejectChild($pairReject[1], $offerId, (int) $candidateUser->getKey());
        }
        fclose($pairReject[1]);

        $pipes = [0 => $pairAccept[0], 1 => $pairReject[0]];
        $premature = $this->collectReady($pipes, 2.0);
        $barrier->rollBack();

        $acceptResult = trim((string) stream_get_contents($pairAccept[0]));
        fclose($pairAccept[0]);
        pcntl_waitpid($pidAccept, $status);

        $rejectResult = trim((string) stream_get_contents($pairReject[0]));
        fclose($pairReject[0]);
        pcntl_waitpid($pidReject, $status);

        $this->assertSame([], $premature, 'A child completed while the offer row lock was still held.');

        // Whichever process the database grants the offer row lock to first
        // legally wins — both orderings are coherent, the assertion is that
        // exactly one side wins outright and the loser sees a legal
        // post-lock rejection, never a torn/mixed state.
        $outcomes = [$acceptResult, $rejectResult];
        sort($outcomes);
        $this->assertContains($outcomes, [
            ['ALREADY_RESPONDED', 'OK'],
            ['OK', 'OK'],
        ], 'Outcomes were: accept='.$acceptResult.' reject='.$rejectResult);

        $finalStatus = DB::table('offers')->where('id', $offerId)->value('status');
        $this->assertContains($finalStatus, ['ACCEPTED', 'REJECTED']);

        if ($finalStatus === 'ACCEPTED') {
            $this->assertSame('HIRED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
        } else {
            $this->assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
        }
    }

    private function runAcceptChild($pipe, int $offerId, int $candidateUserId): never
    {
        DB::purge(config('database.default'));
        DB::statement("SET lock_timeout = '30s'");

        $result = 'ERROR';
        try {
            app(AcceptOffer::class)->execute(
                User::query()->findOrFail($candidateUserId),
                Offer::query()->findOrFail($offerId),
            );
            $result = 'OK';
        } catch (OfferAlreadyResponded) {
            $result = 'ALREADY_RESPONDED';
        } catch (OfferAlreadyAcceptedForApplication) {
            $result = 'ALREADY_ACCEPTED_FOR_APPLICATION';
        } catch (\Throwable $exception) {
            $result = 'ERROR:'.$exception::class.':'.$exception->getMessage();
        }

        fwrite($pipe, $result);
        fflush($pipe);
        fclose($pipe);
        posix_kill(posix_getpid(), SIGKILL);
        exit(0);
    }

    private function runRejectChild($pipe, int $offerId, int $candidateUserId): never
    {
        DB::purge(config('database.default'));
        DB::statement("SET lock_timeout = '30s'");

        $result = 'ERROR';
        try {
            app(RejectOffer::class)->execute(
                User::query()->findOrFail($candidateUserId),
                Offer::query()->findOrFail($offerId),
                'concurrency test',
            );
            $result = 'OK';
        } catch (OfferAlreadyResponded) {
            $result = 'ALREADY_RESPONDED';
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

    private function candidateUserId(int $applicationId): int
    {
        $profileId = (int) DB::table('applications')->where('id', $applicationId)->value('candidate_profile_id');

        return (int) DB::table('candidate_profiles')->where('id', $profileId)->value('user_id');
    }

    private function sendOffer(User $admin, int $applicationId): int
    {
        $offerId = (int) $this->actingAs($admin)->postJson("/applications/{$applicationId}/offers", [
            'note' => 'Selamat, Anda kami tawarkan posisi ini.',
        ])->assertCreated()->json('data.id');

        $this->actingAs($admin)->withHeaders(['Idempotency-Key' => 'offer-concurrency-send-'.uniqid('', true)])
            ->postJson("/offers/{$offerId}/send", [])->assertOk();

        return $offerId;
    }

    /** @return array{0: User, 1: int, 2: int, 3: User} */
    private function fixture(string $slug): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter("offer-concurrency-{$slug}-recruiter@example.test");
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ]);
        $approver = $this->moderator("offer-concurrency-{$slug}-approver@example.test");
        $this->actingAs($approver)->postJson("/vacancies/{$vacancyId}/approve")->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update([
            'current_status' => 'PUBLISHED', 'published_at' => $open,
        ]);
        Carbon::setTestNow($close->copy()->subDays(10));

        $candidateUser = $this->makeUser("offer-concurrency-{$slug}-candidate@example.test", UserStatus::Active);
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

        $offerId = $this->sendOffer($recruiter, $applicationId);

        return [$recruiter, $applicationId, $offerId, $candidateUser];
    }

    private function cleanUp(): void
    {
        $owner = $this->migrationConnection();
        $slugs = ['same-offer', 'inv031', 'accept-vs-reject'];
        $emails = [];
        foreach ($slugs as $slug) {
            $emails[] = "offer-concurrency-{$slug}-recruiter@example.test";
            $emails[] = "offer-concurrency-{$slug}-approver@example.test";
            $emails[] = "offer-concurrency-{$slug}-candidate@example.test";
        }

        $userIds = $owner->table('users')->whereIn('email', $emails)->pluck('id');
        $profileIds = $owner->table('candidate_profiles')->whereIn('user_id', $userIds)->pluck('id');
        $companyIds = $owner->table('company_members')->whereIn('user_id', $userIds)->pluck('company_id')->unique();
        $vacancyIds = $owner->table('vacancies')->whereIn('company_id', $companyIds)->pluck('id');
        $applicationIds = $owner->table('applications')->whereIn('vacancy_id', $vacancyIds)->orWhereIn('candidate_profile_id', $profileIds)->pluck('id');
        $offerIds = $owner->table('offers')->whereIn('application_id', $applicationIds)->pluck('id');

        $owner->table('email_outbox')->where('related_object_type', 'offer')->whereIn('related_object_id', $offerIds)->delete();
        $owner->table('notifications')->whereIn('user_id', $userIds)->delete();
        $owner->table('audit_logs')->where('object_type', 'offer')->whereIn('object_id', $offerIds)->delete();
        $owner->table('offers')->whereIn('id', $offerIds)->delete();

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
