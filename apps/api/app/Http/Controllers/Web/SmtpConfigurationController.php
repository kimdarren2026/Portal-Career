<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Models\User;
use App\Domains\Notification\Actions\TestSmtpConfiguration;
use App\Domains\Notification\Actions\UpdateSmtpConfiguration;
use App\Domains\Notification\Exceptions\SmtpConfigurationConflict;
use App\Domains\Notification\Exceptions\SmtpConfigurationMissing;
use App\Domains\Notification\Models\SmtpConfiguration;
use App\Domains\Notification\Support\SmtpConfigurationPresenter;
use App\Domains\Shared\Support\IdempotencyGuard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\TestSmtpConfigurationRequest;
use App\Http\Requests\Notification\UpdateSmtpConfigurationRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Thin mutation surface for SMTP configuration (FR-NOTIF-005, ADR-015),
 * reclassified `INERTIA_WEB` (browser path = contract URI minus `/api/v1`).
 * The page itself is `SuperAdminPageController::smtpConfigurationIndex`.
 *
 * `SUPER_ADMIN` only on every method (Policy checked before any read or
 * write). The Action owns the transaction and the single-active invariant.
 * No response, here or in the Action, ever returns the credential — only
 * `secret_configured`.
 */
final class SmtpConfigurationController extends Controller
{
    public function __construct(private readonly IdempotencyGuard $idempotency) {}

    /** `GET /admin/smtp-configuration` — safe metadata, or `null` when none exists. */
    public function show(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        if (Gate::forUser($actor)->denies('view', SmtpConfiguration::class)) {
            return $this->forbidden($request);
        }

        return ContractResponse::success($request, ['configuration' => $this->safeCurrent()]);
    }

    /** `PUT /admin/smtp-configuration`. */
    public function update(UpdateSmtpConfigurationRequest $request, UpdateSmtpConfiguration $action): JsonResponse
    {
        $actor = $this->actor($request);
        if (Gate::forUser($actor)->denies('update', SmtpConfiguration::class)) {
            return $this->forbidden($request);
        }

        // Idempotency-Key fingerprint deliberately excludes the raw secret.
        $begin = $this->idempotency->begin(
            $request->header('Idempotency-Key'),
            $actor,
            'smtp.configuration.update',
            ['body' => $request->safe()->except('password'), 'credential_changed' => $request->secretProvided()],
        );

        if ($begin['status'] === 'reused') {
            return ContractResponse::error($request, 'IDEMPOTENCY_KEY_REUSED', 409, 'Kunci idempotensi digunakan ulang dengan payload berbeda.');
        }
        if ($begin['status'] === 'in_progress') {
            return ContractResponse::error($request, 'IDEMPOTENT_REPLAY_IN_PROGRESS', 409, 'Permintaan sebelumnya dengan kunci ini masih diproses.');
        }
        if ($begin['status'] === 'replay') {
            return ContractResponse::success($request, $begin['response_body']['data'] ?? [], $begin['response_status'])
                ->header('Idempotency-Replayed', 'true');
        }

        $id = $begin['id'] ?? null;

        try {
            $row = $action->execute($actor, $request->configData(), $request->secretProvided(), $request->plainSecret());
            $data = ['configuration' => SmtpConfigurationPresenter::safe($row)];
        } catch (SmtpConfigurationConflict) {
            $this->idempotency->fail($id);

            return ContractResponse::error(
                $request,
                'CONFLICT',
                409,
                'Konfigurasi SMTP aktif sedang diubah oleh permintaan lain. Silakan coba lagi.',
            );
        } catch (\Throwable $e) {
            $this->idempotency->fail($id);

            throw $e;
        }

        $this->idempotency->complete($id, 200, ['data' => $data]);

        return ContractResponse::success($request, $data);
    }

    /** `POST /admin/smtp-configuration/test`. */
    public function test(TestSmtpConfigurationRequest $request, TestSmtpConfiguration $action): JsonResponse
    {
        $actor = $this->actor($request);
        if (Gate::forUser($actor)->denies('test', SmtpConfiguration::class)) {
            return $this->forbidden($request);
        }

        try {
            $outcome = $action->execute($actor, (string) $request->validated('recipient'));
        } catch (SmtpConfigurationMissing) {
            return ContractResponse::error($request, 'SERVICE_UNAVAILABLE', 503, 'Belum ada konfigurasi SMTP yang dapat diuji.');
        }

        return ContractResponse::success($request, [
            'result' => $outcome->success ? 'SUCCESS' : 'FAILURE',
            'message' => $outcome->safeMessage,
            'tested_at' => now()->toIso8601String(),
        ]);
    }

    private function safeCurrent(): ?array
    {
        $current = SmtpConfiguration::query()->where('is_active', true)->first()
            ?? SmtpConfiguration::query()->orderByDesc('id')->first();

        return $current === null ? null : SmtpConfigurationPresenter::safe($current);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function forbidden(Request $request): JsonResponse
    {
        return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
    }
}
