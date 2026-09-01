<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Notification\Models\SmtpConfiguration;
use App\Domains\Notification\Support\SmtpTestOutcome;
use App\Domains\Notification\Support\SmtpTestSender;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * SMTP Configuration Foundation (FR-NOTIF-005, ADR-015, INV-035, INV-036).
 * Backend runtime: read / update / test, SUPER_ADMIN only, write-only
 * application-encrypted secret, single active configuration.
 */
final class SmtpConfigurationTest extends VacancyTestCase
{
    private function superAdmin(string $email): User
    {
        $u = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($u, RoleCode::SuperAdmin);

        return $u;
    }

    private function auditor(string $email): User
    {
        $u = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($u, RoleCode::Auditor);

        return $u;
    }

    private function candidate(string $email): User
    {
        $u = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($u, RoleCode::CandidateAlumni);

        return $u;
    }

    private function hrAdmin(string $email): User
    {
        $u = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($u, RoleCode::HrAdmin);

        return $u;
    }

    /** @param array<string, mixed> $overrides */
    private function seedConfig(array $overrides = [], ?string $plainSecret = 'stored-secret'): SmtpConfiguration
    {
        $row = new SmtpConfiguration();
        $row->forceFill(array_merge([
            'host' => 'mail.example.test',
            'port' => 587,
            'encryption_mode' => 'STARTTLS',
            'username' => 'mailer',
            'encrypted_password' => $plainSecret === null ? null : Crypt::encryptString($plainSecret),
            'from_address' => 'noreply@example.test',
            'from_name' => 'Portal',
            'reply_to_address' => null,
            'timeout_seconds' => 30,
            'max_attempts' => 3,
            'retry_backoff_seconds' => 60,
            'is_active' => true,
            'last_tested_at' => null,
            'last_test_result' => 'NOT_TESTED',
            'updated_by_user_id' => $this->superAdmin('seed-'.uniqid().'@example.test')->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides))->save();

        return $row->refresh();
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'host' => 'smtp.new.test',
            'port' => 2525,
            'encryption_mode' => 'TLS',
            'username' => 'svc',
            'from_address' => 'from@new.test',
            'from_name' => 'New Sender',
            'reply_to_address' => 'reply@new.test',
            'timeout_seconds' => 15,
            'max_attempts' => 5,
            'retry_backoff_seconds' => 120,
            'is_active' => true,
        ], $overrides);
    }

    private function fakeSender(SmtpTestOutcome $outcome): void
    {
        $this->app->instance(SmtpTestSender::class, new class($outcome) implements SmtpTestSender
        {
            public function __construct(private readonly SmtpTestOutcome $outcome) {}

            public function send(SmtpConfiguration $config, string $recipient): SmtpTestOutcome
            {
                return $this->outcome;
            }
        });
    }

    public function test_smtp_configuration_json_surface_denied_to_auditor_and_every_other_persona(): void
    {
        $this->seedConfig();
        [$recruiter] = $this->companyWithRecruiter('smtp-deny-recruiter@example.test', \App\Domains\Company\Enums\CompanyStatus::Verified);

        $denied = [
            $this->auditor('smtp-deny-auditor@example.test'),
            $recruiter,
            $this->candidate('smtp-deny-candidate@example.test'),
            $this->hrAdmin('smtp-deny-hr@example.test'),
            $this->moderator('smtp-deny-cc@example.test'),
        ];

        foreach ($denied as $user) {
            $this->actingAs($user)->putJson('/admin/smtp-configuration', $this->validPayload())->assertStatus(403);
            $this->actingAs($user)->postJson('/admin/smtp-configuration/test', ['recipient' => 'x@example.test'])->assertStatus(403);
            $this->actingAs($user)->getJson('/admin/smtp-configuration')->assertStatus(403);
        }
    }

    public function test_get_json_surface_returns_safe_metadata_only(): void
    {
        $this->seedConfig();
        $admin = $this->superAdmin('smtp-get-json@example.test');

        $response = $this->actingAs($admin)->getJson('/admin/smtp-configuration')->assertOk()
            ->assertJsonPath('data.configuration.secret_configured', true)
            ->assertJsonMissingPath('data.configuration.encrypted_password');
        $this->assertStringNotContainsString('stored-secret', $response->getContent());
    }

    public function test_update_encrypts_secret_at_rest_and_never_returns_it(): void
    {
        $admin = $this->superAdmin('smtp-update@example.test');

        $response = $this->actingAs($admin)->putJson('/admin/smtp-configuration', $this->validPayload([
            'password' => 'plaintext-credential-123',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.configuration.secret_configured', true)
            ->assertJsonMissingPath('data.configuration.encrypted_password')
            ->assertJsonMissingPath('data.configuration.password');

        $this->assertStringNotContainsString('plaintext-credential-123', $response->getContent());

        $stored = DB::table('smtp_configurations')->where('is_active', true)->value('encrypted_password');
        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('plaintext-credential-123', (string) $stored);
        $this->assertSame('plaintext-credential-123', Crypt::decryptString((string) $stored));
    }

    public function test_update_without_password_preserves_existing_secret(): void
    {
        $existing = $this->seedConfig([], 'keep-me-secret');
        $admin = $this->superAdmin('smtp-preserve@example.test');

        $this->actingAs($admin)->putJson('/admin/smtp-configuration', $this->validPayload())
            ->assertOk()->assertJsonPath('data.configuration.secret_configured', true);

        $active = DB::table('smtp_configurations')->where('is_active', true)->first();
        $this->assertSame('keep-me-secret', Crypt::decryptString((string) $active->encrypted_password));
        $this->assertNotSame($existing->id, $active->id, 'a new row is written and the old one retained');
    }

    public function test_update_with_explicit_null_clears_secret(): void
    {
        $this->seedConfig([], 'to-be-cleared');
        $admin = $this->superAdmin('smtp-clear@example.test');

        $this->actingAs($admin)->putJson('/admin/smtp-configuration', $this->validPayload(['password' => null]))
            ->assertOk()->assertJsonPath('data.configuration.secret_configured', false);

        $active = DB::table('smtp_configurations')->where('is_active', true)->first();
        $this->assertNull($active->encrypted_password);
    }

    public function test_update_enforces_single_active_invariant_and_retains_history(): void
    {
        $old = $this->seedConfig();
        $admin = $this->superAdmin('smtp-single-active@example.test');

        $this->actingAs($admin)->putJson('/admin/smtp-configuration', $this->validPayload(['is_active' => true]))->assertOk();

        $this->assertSame(1, DB::table('smtp_configurations')->where('is_active', true)->count());
        $this->assertFalse((bool) DB::table('smtp_configurations')->where('id', $old->id)->value('is_active'));
        $this->assertSame(2, DB::table('smtp_configurations')->count(), 'previous row retained as history');
    }

    public function test_update_audit_records_event_without_any_secret(): void
    {
        $admin = $this->superAdmin('smtp-audit@example.test');

        $this->actingAs($admin)->putJson('/admin/smtp-configuration', $this->validPayload(['password' => 'super-topsecret-value']))->assertOk();

        $row = DB::table('audit_logs')->where('action', 'smtp_configuration_updated')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertStringNotContainsString('super-topsecret-value', (string) $row->change_summary);
        $summary = json_decode((string) $row->change_summary, true);
        $this->assertTrue($summary['credential_changed']);
        $this->assertArrayNotHasKey('password', $summary);
        $this->assertArrayNotHasKey('encrypted_password', $summary);
    }

    public function test_validation_rejects_out_of_range_and_bad_enum(): void
    {
        $admin = $this->superAdmin('smtp-validate@example.test');

        $this->actingAs($admin)->putJson('/admin/smtp-configuration', $this->validPayload([
            'port' => 70000,
            'encryption_mode' => 'SSLv3',
            'max_attempts' => 0,
        ]))->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_test_email_success_updates_row_and_audits_without_secret(): void
    {
        $this->seedConfig();
        $this->fakeSender(SmtpTestOutcome::success());
        $admin = $this->superAdmin('smtp-test-ok@example.test');

        $this->actingAs($admin)->postJson('/admin/smtp-configuration/test', ['recipient' => 'ops@example.test'])
            ->assertOk()
            ->assertJsonPath('data.result', 'SUCCESS');

        $active = DB::table('smtp_configurations')->where('is_active', true)->first();
        $this->assertSame('SUCCESS', $active->last_test_result);
        $this->assertNotNull($active->last_tested_at);

        $audit = DB::table('audit_logs')->where('action', 'smtp_configuration_tested')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('stored-secret', (string) $audit->change_summary);
        $this->assertSame('SUCCESS', json_decode((string) $audit->change_summary, true)['result']);
    }

    public function test_test_email_failure_is_sanitized_and_not_an_exception(): void
    {
        $this->seedConfig();
        $this->fakeSender(SmtpTestOutcome::failure('Pengiriman uji gagal. Periksa host, port, mode enkripsi, dan kredensial SMTP.'));
        $admin = $this->superAdmin('smtp-test-fail@example.test');

        $response = $this->actingAs($admin)->postJson('/admin/smtp-configuration/test', ['recipient' => 'ops@example.test']);

        $response->assertOk()->assertJsonPath('data.result', 'FAILURE');
        $body = $response->getContent();
        $this->assertStringNotContainsString('Exception', $body);
        $this->assertStringNotContainsString('stored-secret', $body);
        $this->assertStringNotContainsString('mail.example.test', $body);

        $this->assertSame('FAILURE', DB::table('smtp_configurations')->where('is_active', true)->value('last_test_result'));
    }

    public function test_test_email_returns_503_when_no_configuration(): void
    {
        $admin = $this->superAdmin('smtp-test-none@example.test');

        $this->actingAs($admin)->postJson('/admin/smtp-configuration/test', ['recipient' => 'ops@example.test'])
            ->assertStatus(503)->assertJsonPath('error.code', 'SERVICE_UNAVAILABLE');
    }

    public function test_smtp_operations_write_no_notification_or_outbox_row(): void
    {
        $this->seedConfig();
        $this->fakeSender(SmtpTestOutcome::success());
        $admin = $this->superAdmin('smtp-outbox@example.test');

        $notifBefore = DB::table('notifications')->count();
        $outboxBefore = DB::table('email_outbox')->count();

        $this->actingAs($admin)->putJson('/admin/smtp-configuration', $this->validPayload(['password' => 'x']))->assertOk();
        $this->actingAs($admin)->postJson('/admin/smtp-configuration/test', ['recipient' => 'ops@example.test'])->assertOk();

        $this->assertSame($notifBefore, DB::table('notifications')->count());
        $this->assertSame($outboxBefore, DB::table('email_outbox')->count());
    }

    public function test_update_is_idempotent_with_a_reused_key(): void
    {
        $admin = $this->superAdmin('smtp-idem@example.test');
        $key = 'idem-smtp-'.uniqid();

        $first = $this->actingAs($admin)->putJson('/admin/smtp-configuration', $this->validPayload(), ['Idempotency-Key' => $key]);
        $first->assertOk();

        $second = $this->actingAs($admin)->putJson('/admin/smtp-configuration', $this->validPayload(), ['Idempotency-Key' => $key]);
        $second->assertOk()->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame(1, DB::table('smtp_configurations')->count());
    }

    /**
     * Post-v11 hardening: a first-write race for the single-active slot
     * (`uq_smtp_configurations_active`) must never surface as a 500. Here a
     * competing ACTIVE row is injected exactly once, during the Action's own
     * insert — the Action retries and completes normally.
     */
    public function test_first_write_race_is_retried_and_succeeds(): void
    {
        $admin = $this->superAdmin('smtp-race-retry@example.test');
        $injected = 0;

        SmtpConfiguration::creating(function () use (&$injected, $admin): void {
            if ($injected++ > 0) {
                return;
            }
            DB::table('smtp_configurations')->insert([
                'host' => 'rival.test', 'port' => 25, 'encryption_mode' => 'NONE',
                'from_address' => 'rival@rival.test', 'max_attempts' => 3, 'retry_backoff_seconds' => 60,
                'is_active' => true, 'last_test_result' => 'NOT_TESTED',
                'updated_by_user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        try {
            $this->actingAs($admin)->putJson('/admin/smtp-configuration', $this->validPayload())
                ->assertOk()
                ->assertJsonPath('data.configuration.host', 'smtp.new.test')
                ->assertJsonPath('data.configuration.is_active', true);
        } finally {
            SmtpConfiguration::flushEventListeners();
        }

        $this->assertSame(1, DB::table('smtp_configurations')->where('is_active', true)->count());
    }

    /**
     * If the race cannot be resolved by the single retry, the loser gets a
     * safe `409 CONFLICT` — never a SQLSTATE, constraint name, SQL, or 500.
     */
    public function test_unresolvable_active_race_maps_to_409_conflict_not_500(): void
    {
        $admin = $this->superAdmin('smtp-race-conflict@example.test');

        SmtpConfiguration::creating(function () use ($admin): void {
            DB::table('smtp_configurations')->insert([
                'host' => 'rival.test', 'port' => 25, 'encryption_mode' => 'NONE',
                'from_address' => 'rival@rival.test', 'max_attempts' => 3, 'retry_backoff_seconds' => 60,
                'is_active' => true, 'last_test_result' => 'NOT_TESTED',
                'updated_by_user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        try {
            $response = $this->actingAs($admin)->putJson('/admin/smtp-configuration', $this->validPayload());
        } finally {
            SmtpConfiguration::flushEventListeners();
        }

        $response->assertStatus(409)->assertJsonPath('error.code', 'CONFLICT');
        $body = $response->getContent();
        foreach (['SQLSTATE', '23505', 'uq_smtp_configurations_active', 'QueryException', 'Exception', 'insert into'] as $leak) {
            $this->assertStringNotContainsString($leak, $body);
        }
    }
}
