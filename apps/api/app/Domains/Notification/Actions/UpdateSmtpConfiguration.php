<?php

declare(strict_types=1);

namespace App\Domains\Notification\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Notification\Models\SmtpConfiguration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * `PUT /admin/smtp-configuration` (API_CONTRACT.md, FR-NOTIF-005, ADR-015).
 *
 * Writes a new configuration row and deactivates the previously-active one,
 * which is retained as history (ADR-015 / INV-036). Everything happens in one
 * transaction with the active row locked, so two concurrent writers can never
 * both leave an active row — the DB partial unique index
 * `uq_smtp_configurations_active` is the final authority.
 *
 * Credential semantics (frozen):
 * - `password` omitted   → the existing encrypted value is copied unchanged;
 * - `password` provided  → the ciphertext is replaced wholesale;
 * - `password` null      → an explicit clear.
 * `credential_changed` is true whenever the `password` key was present.
 *
 * Encryption is application-level via {@see Crypt} (Laravel `Encrypter`,
 * AES-256-CBC + HMAC) keyed by `APP_KEY`, which lives in deployment secret
 * configuration and never in the database (INV-035). Plaintext is never
 * written, not even transiently.
 *
 * Audit: `smtp_configuration_updated` with `credential_changed` and the names
 * of the non-secret fields that changed — never a credential value (INV-035).
 */
final class UpdateSmtpConfiguration
{
    /** Non-secret fields compared for the audit `changed_fields` list. */
    private const TRACKED_FIELDS = [
        'host', 'port', 'encryption_mode', 'username', 'from_address', 'from_name',
        'reply_to_address', 'timeout_seconds', 'max_attempts', 'retry_backoff_seconds', 'is_active',
    ];

    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array<string, mixed>  $data   validated non-secret fields (all of self::TRACKED_FIELDS)
     * @param  bool                  $secretProvided  whether the request carried a `password` key
     * @param  string|null           $plainSecret     its value when present (null = explicit clear)
     */
    public function execute(User $actor, array $data, bool $secretProvided, ?string $plainSecret): SmtpConfiguration
    {
        return DB::transaction(function () use ($actor, $data, $secretProvided, $plainSecret): SmtpConfiguration {
            $base = SmtpConfiguration::query()->where('is_active', true)->lockForUpdate()->first()
                ?? SmtpConfiguration::query()->orderByDesc('id')->lockForUpdate()->first();

            $encrypted = $secretProvided
                ? ($plainSecret === null ? null : Crypt::encryptString($plainSecret))
                : $base?->getAttribute('encrypted_password');

            // Deactivate whatever is currently active (at most one row); the new
            // row then carries the requested active state. Zero active is valid.
            SmtpConfiguration::query()->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => now()]);

            $row = new SmtpConfiguration();
            $row->forceFill([
                'host' => $data['host'],
                'port' => $data['port'],
                'encryption_mode' => $data['encryption_mode'],
                'username' => $data['username'] ?? null,
                'encrypted_password' => $encrypted,
                'from_address' => $data['from_address'],
                'from_name' => $data['from_name'] ?? null,
                'reply_to_address' => $data['reply_to_address'] ?? null,
                'timeout_seconds' => $data['timeout_seconds'] ?? null,
                'max_attempts' => $data['max_attempts'],
                'retry_backoff_seconds' => $data['retry_backoff_seconds'],
                'is_active' => (bool) $data['is_active'],
                'last_tested_at' => null,
                'last_test_result' => 'NOT_TESTED',
                'updated_by_user_id' => $actor->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->save();

            $this->audit->record(
                'smtp_configuration_updated',
                $actor,
                'smtp_configuration',
                (int) $row->getKey(),
                [
                    'credential_changed' => $secretProvided,
                    'changed_fields' => $this->changedFields($base, $data),
                ],
            );

            return $row->refresh();
        });
    }

    /**
     * Names only — never values — of the non-secret fields that differ from
     * the previous configuration. `['*']` when there was no previous row.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function changedFields(?SmtpConfiguration $base, array $data): array
    {
        if ($base === null) {
            return ['*'];
        }

        $changed = [];
        foreach (self::TRACKED_FIELDS as $field) {
            $new = $field === 'is_active' ? (bool) ($data[$field] ?? false) : ($data[$field] ?? null);
            $old = $field === 'is_active' ? (bool) $base->getAttribute($field) : $base->getAttribute($field);
            if ($new != $old) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
