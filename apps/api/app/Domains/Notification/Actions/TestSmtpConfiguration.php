<?php

declare(strict_types=1);

namespace App\Domains\Notification\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Notification\Exceptions\SmtpConfigurationMissing;
use App\Domains\Notification\Models\SmtpConfiguration;
use App\Domains\Notification\Support\SmtpTestOutcome;
use App\Domains\Notification\Support\SmtpTestSender;
use Illuminate\Support\Facades\DB;

/**
 * `POST /admin/smtp-configuration/test` (API_CONTRACT.md, FR-NOTIF-005).
 *
 * Uses the stored (active) configuration to attempt one diagnostic delivery.
 * The test bypasses `email_outbox` and creates no notification — configuring
 * or testing mail must not itself depend on the business mail pipeline. It
 * records `last_tested_at` and `last_test_result` on the configuration row
 * (that row is not append-only) and audits `smtp_configuration_tested` with
 * the result and recipient — never the credential (INV-035).
 *
 * A delivery failure is still a successful *execution*: the outcome carries
 * `result: FAILURE` with a sanitized message, not an exception.
 */
final class TestSmtpConfiguration
{
    public function __construct(
        private readonly SmtpTestSender $sender,
        private readonly AuditWriter $audit,
    ) {}

    public function execute(User $actor, string $recipient): SmtpTestOutcome
    {
        $config = SmtpConfiguration::query()->where('is_active', true)->first()
            ?? SmtpConfiguration::query()->orderByDesc('id')->first();

        if ($config === null) {
            throw new SmtpConfigurationMissing();
        }

        $outcome = $this->sender->send($config, $recipient);
        $result = $outcome->success ? 'SUCCESS' : 'FAILURE';

        DB::table('smtp_configurations')->where('id', $config->getKey())->update([
            'last_tested_at' => now(),
            'last_test_result' => $result,
            'updated_at' => now(),
        ]);

        $this->audit->record(
            'smtp_configuration_tested',
            $actor,
            'smtp_configuration',
            (int) $config->getKey(),
            ['result' => $result, 'recipient' => $recipient],
        );

        return $outcome;
    }
}
