<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domains\Notification\Support\OutboxMailSender;
use App\Jobs\DeliverEmailOutboxMessage;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Notification\Support\OutboxWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Identity\IdentityTestCase;

/**
 * PGC-V1 / PD-B — the transactional email-outbox delivery state machine.
 */
final class EmailOutboxDeliveryTest extends IdentityTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function writeRow(): int
    {
        return app(OutboxWriter::class)->queue(
            'dest@example.test',
            'application.submitted.candidate',
            ['application_id' => 7, 'vacancy_id' => 3],
            'application',
            7,
        );
    }

    private function bindFailingSender(): void
    {
        $this->app->bind(OutboxMailSender::class, fn () => new class extends OutboxMailSender {
            public function send(string $recipient, string $subject, string $body): void
            {
                throw new RuntimeException('smtp connect failed: host=secret.example password=hunter2');
            }
        });
    }

    public function test_successful_delivery_moves_the_row_to_sent(): void
    {
        $id = $this->writeRow();
        (new DeliverEmailOutboxMessage($id))->handle(
            app(OutboxMailSender::class),
            app(\App\Domains\Notification\Support\OutboxDeliveryPolicy::class),
            app(\App\Domains\Notification\Support\EmailTemplateCatalog::class),
        );

        $row = DB::table('email_outbox')->find($id);
        self::assertSame('SENT', $row->status);
        self::assertNotNull($row->sent_at);
        self::assertNull($row->last_error_summary);
    }

    public function test_temporary_failure_schedules_a_retry_with_backoff_and_a_sanitized_summary(): void
    {
        config(['outbox.max_attempts' => 3, 'outbox.retry_backoff_seconds' => 120]);
        $this->bindFailingSender();
        Carbon::setTestNow('2026-09-10T00:00:00+00:00');

        $id = $this->writeRow();
        $this->runJob($id);

        $row = DB::table('email_outbox')->find($id);
        self::assertSame('FAILED_RETRYABLE', $row->status);
        self::assertSame(1, (int) $row->attempt_count);
        self::assertNotNull($row->next_attempt_at);
        self::assertTrue(Carbon::parse($row->next_attempt_at)->gt(now()));
        // Sanitized: no host, no password, no raw exception text.
        self::assertStringNotContainsString('hunter2', (string) $row->last_error_summary);
        self::assertStringNotContainsString('secret.example', (string) $row->last_error_summary);
        self::assertStringNotContainsString('password', (string) $row->last_error_summary);
    }

    public function test_exhausting_max_attempts_moves_the_row_to_dead_letter(): void
    {
        config(['outbox.max_attempts' => 2, 'outbox.retry_backoff_seconds' => 1]);
        $this->bindFailingSender();

        $id = $this->writeRow();

        $this->runJob($id); // attempt 1 -> FAILED_RETRYABLE
        DB::table('email_outbox')->where('id', $id)->update(['next_attempt_at' => now()->subMinute()]);
        $this->runJob($id); // attempt 2 -> DEAD_LETTER

        $row = DB::table('email_outbox')->find($id);
        self::assertSame('DEAD_LETTER', $row->status);
        self::assertSame(2, (int) $row->attempt_count);
        self::assertNull($row->next_attempt_at);
    }

    public function test_an_already_sent_row_is_never_re_delivered_duplicate_protection(): void
    {
        $id = $this->writeRow();
        $this->runJob($id);
        self::assertSame('SENT', DB::table('email_outbox')->where('id', $id)->value('status'));

        // A second (duplicate) job for the same row is a no-op.
        $this->bindFailingSender();
        $this->runJob($id);
        self::assertSame('SENT', DB::table('email_outbox')->where('id', $id)->value('status'));
    }

    public function test_backoff_not_yet_elapsed_defers_the_row(): void
    {
        config(['outbox.max_attempts' => 5, 'outbox.retry_backoff_seconds' => 600]);
        $this->bindFailingSender();
        $id = $this->writeRow();
        $this->runJob($id); // -> FAILED_RETRYABLE, next_attempt_at in the future

        // Re-running before the backoff elapses must not increment the attempt.
        $this->runJob($id);
        self::assertSame(1, (int) DB::table('email_outbox')->where('id', $id)->value('attempt_count'));
    }

    public function test_super_admin_can_requeue_a_dead_letter_row(): void
    {
        config(['outbox.max_attempts' => 1, 'outbox.retry_backoff_seconds' => 1]);
        $this->bindFailingSender();
        $id = $this->writeRow();
        $this->runJob($id);
        self::assertSame('DEAD_LETTER', DB::table('email_outbox')->where('id', $id)->value('status'));

        $superAdmin = $this->makeUser('outbox-sa@example.test', UserStatus::Active);
        $this->assignRole($superAdmin, RoleCode::SuperAdmin);

        // Requeue succeeds and re-drives; the failing sender is still bound, so
        // it lands back in DEAD_LETTER (max_attempts = 1) but the row was reset.
        $this->actingAs($superAdmin)->postJson("/admin/email-outbox/{$id}/requeue")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'email_outbox_requeued', 'object_id' => $id]);

        $recruiter = $this->makeUser('outbox-notadmin@example.test', UserStatus::Active);
        $this->assignRole($recruiter, RoleCode::CompanyRecruiter);
        $this->actingAs($recruiter)->postJson("/admin/email-outbox/{$id}/requeue")
            ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_a_sweep_re_drives_a_pending_row_written_before_a_worker_existed(): void
    {
        // Simulate a legacy row: insert directly, PENDING, no dispatch.
        $id = (int) DB::table('email_outbox')->insertGetId([
            'recipient' => 'legacy@example.test',
            'template_reference' => 'identity.password-changed',
            'payload_reference' => json_encode(['user_name' => 'X'], JSON_THROW_ON_ERROR),
            'status' => 'PENDING', 'attempt_count' => 0, 'next_attempt_at' => null, 'created_at' => now(),
        ]);

        $this->artisan('outbox:sweep')->assertSuccessful();

        self::assertSame('SENT', DB::table('email_outbox')->where('id', $id)->value('status'));
    }

    private function runJob(int $id): void
    {
        (new DeliverEmailOutboxMessage($id))->handle(
            app(OutboxMailSender::class),
            app(\App\Domains\Notification\Support\OutboxDeliveryPolicy::class),
            app(\App\Domains\Notification\Support\EmailTemplateCatalog::class),
        );
    }
}
