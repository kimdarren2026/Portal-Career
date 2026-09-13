<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Candidate\Support\CandidateProfileResolver;
use App\Domains\ExternalApply\Actions\ConfirmExternalApply;
use App\Domains\ExternalApply\Actions\StartExternalApply;
use App\Domains\ExternalApply\Models\ExternalApplyEvent;
use App\Domains\ExternalApply\Queries\ListCandidateExternalApplyEvents;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\IdempotencyGuard;
use App\Http\Requests\ExternalApply\ConfirmExternalApplyRequest;
use App\Http\Requests\ExternalApply\StartExternalApplyRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * External Apply runtime (API_CONTRACT.md Part VII · FR-EXT-001..004 ·
 * INV-012, INV-024). Contract Surface is `VERSIONED_API`; like every other
 * business domain in this MVP it is realized on the browser session-guard
 * surface (`routes/api.php` stays intentionally empty) — a transport mapping
 * only, no business-behaviour change.
 *
 * `start` records a candidate leaving for an external ATS and hands back the
 * stored destination URL; it creates NO application row. `confirm` records a
 * legitimate confirmation source's outcome. `events` is the candidate's OWN
 * external-apply history. The `EXTERNAL_APPLY` recruitment-outcome path stays
 * deferred (Part X item 46) and is untouched here.
 */
final class ExternalApplyController extends CandidateController
{
    public function __construct(
        CandidateProfileResolver $profiles,
        private readonly IdempotencyGuard $idempotency,
    ) {
        parent::__construct($profiles);
    }

    /** `POST /vacancies/{vacancy}/external-apply/start`. */
    public function start(StartExternalApplyRequest $request, int $vacancy, StartExternalApply $action): JsonResponse
    {
        $actor = $this->actor($request);
        $profile = $this->ownProfile($request);

        return $this->idempotent($request, $actor, 'external_apply.start', $vacancy, function () use ($actor, $profile, $vacancy, $request, $action): array {
            $event = $action->execute($actor, $profile, $vacancy, $request->validated());

            return [
                'external_apply_event_id' => (int) $event->getKey(),
                'event_type' => $event->event_type,
                'destination_url' => $event->destination_url_reference,
                'confirmation_status' => $event->confirmation_status,
            ];
        }, 201);
    }

    /** `POST /external-apply-events/{event}/confirm`. */
    public function confirm(ConfirmExternalApplyRequest $request, int $event, ConfirmExternalApply $action): JsonResponse
    {
        $actor = $this->actor($request);

        return $this->idempotent($request, $actor, 'external_apply.confirm', $event, function () use ($actor, $event, $request, $action): array {
            $confirmed = $action->execute($actor, $event, $request->validated());

            return [
                'external_apply_event_id' => (int) $confirmed->getKey(),
                'confirmation_status' => $confirmed->confirmation_status,
                'confirmation_source' => $confirmed->confirmation_source,
                'confirmed_at' => $confirmed->confirmed_at?->toIso8601String(),
            ];
        });
    }

    /** `GET /candidate/external-apply-events`. */
    public function events(Request $request, ListCandidateExternalApplyEvents $query): JsonResponse
    {
        $profile = $this->ownProfile($request);
        $events = $query->execute($profile);

        return ContractResponse::success($request, [
            'items' => $events->items(),
            'pagination' => [
                'page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'last_page' => $events->lastPage(),
            ],
        ]);
    }

    /** @param callable(): array<string, mixed> $execute */
    private function idempotent(Request $request, User $actor, string $operation, int $subjectId, callable $execute, int $successStatus = 200): JsonResponse
    {
        $begin = $this->idempotency->begin(
            $request->header('Idempotency-Key'),
            $actor,
            $operation,
            ['subject' => $subjectId, 'body' => $request->all()],
        );

        if ($begin['status'] === 'reused') {
            return ContractResponse::error($request, 'IDEMPOTENCY_KEY_REUSED', 409, 'Kunci idempotensi digunakan ulang dengan payload berbeda.');
        }
        if ($begin['status'] === 'in_progress') {
            return ContractResponse::error($request, 'IDEMPOTENT_REPLAY_IN_PROGRESS', 409, 'Permintaan sebelumnya dengan kunci ini masih diproses.');
        }
        if ($begin['status'] === 'replay') {
            return ContractResponse::success($request, $begin['response_body']['data'] ?? [], $begin['response_status']);
        }

        $id = $begin['id'] ?? null;

        try {
            $data = $execute();
        } catch (Throwable $exception) {
            $this->idempotency->fail($id);

            throw $exception;
        }

        $this->idempotency->complete($id, $successStatus, ['data' => $data]);

        return ContractResponse::success($request, $data, $successStatus);
    }
}
