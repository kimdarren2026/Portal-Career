<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `Idempotency-Key` handling exactly as API_CONTRACT.md Part I §7 defines it,
 * stored in the frozen `idempotency_keys` table (DB-1).
 *
 * Scope is key + actor + operation, enforced by `uq_idempotency_keys_scope`:
 * a key from one actor never matches another's. A replay with the same payload
 * returns the retained response; with a different payload it is
 * `IDEMPOTENCY_KEY_REUSED`; while the first request is still running it is
 * `IDEMPOTENT_REPLAY_IN_PROGRESS`. A missing key is accepted — the caller
 * simply forfeits replay protection, and the operation's own transition rule
 * remains the guard.
 */
final class IdempotencyGuard
{
    public const STATE_PROCESSING = 'PROCESSING';
    public const STATE_COMPLETED = 'COMPLETED';
    public const STATE_FAILED = 'FAILED';

    /** @return array{status: string, id?: int, response_status?: int, response_body?: array<string, mixed>} */
    public function begin(?string $key, User $actor, string $operation, mixed $payload): array
    {
        if ($key === null || trim($key) === '') {
            return ['status' => 'proceed'];
        }

        $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $existing = $this->find($key, $actor, $operation);

        if ($existing === null) {
            // Wrapped so a lost insert race rolls back only its own savepoint:
            // PostgreSQL aborts the whole transaction on an unhandled violation.
            try {
                $id = DB::transaction(fn (): int => (int) DB::table('idempotency_keys')->insertGetId([
                    'idempotency_key' => $key,
                    'actor_user_id' => $actor->getKey(),
                    'actor_context' => 'USER',
                    'operation' => $operation,
                    'request_fingerprint' => $fingerprint,
                    'state' => self::STATE_PROCESSING,
                    'created_at' => now(),
                ]));

                return ['status' => 'proceed', 'id' => $id];
            } catch (Throwable) {
                $existing = $this->find($key, $actor, $operation);
                if ($existing === null) {
                    return ['status' => 'proceed'];
                }
            }
        }

        if ($existing->request_fingerprint !== $fingerprint) {
            return ['status' => 'reused'];
        }

        if ($existing->state === self::STATE_PROCESSING) {
            return ['status' => 'in_progress'];
        }

        if ($existing->state === self::STATE_COMPLETED && $existing->response_status !== null) {
            return [
                'status' => 'replay',
                'response_status' => (int) $existing->response_status,
                'response_body' => json_decode((string) $existing->response_body, true) ?: [],
            ];
        }

        // A previously FAILED attempt left no business fact, so the caller may retry.
        DB::table('idempotency_keys')->where('id', $existing->id)
            ->update(['state' => self::STATE_PROCESSING, 'completed_at' => null]);

        return ['status' => 'proceed', 'id' => (int) $existing->id];
    }

    private function find(string $key, User $actor, string $operation): ?object
    {
        return DB::table('idempotency_keys')
            ->where('idempotency_key', $key)
            ->where('operation', $operation)
            ->where('actor_user_id', $actor->getKey())
            ->first();
    }

    /** @param array<string, mixed> $body */
    public function complete(?int $id, int $status, array $body): void
    {
        if ($id === null) {
            return;
        }

        DB::table('idempotency_keys')->where('id', $id)->update([
            'state' => self::STATE_COMPLETED,
            'response_status' => $status,
            'response_body' => json_encode($body, JSON_THROW_ON_ERROR),
            'completed_at' => now(),
        ]);
    }

    public function fail(?int $id): void
    {
        if ($id === null) {
            return;
        }

        DB::table('idempotency_keys')->where('id', $id)->update([
            'state' => self::STATE_FAILED,
            'completed_at' => now(),
        ]);
    }
}
