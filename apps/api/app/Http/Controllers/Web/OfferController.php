<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Exceptions\ApplicationNotFound;
use App\Domains\Application\Support\ApplicationScope;
use App\Domains\Application\Support\RecruiterApplicationScope;
use App\Domains\Candidate\Support\CandidateProfileResolver;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Offering\Actions\AcceptOffer;
use App\Domains\Recruitment\Offering\Actions\CreateOffer;
use App\Domains\Recruitment\Offering\Actions\RejectOffer;
use App\Domains\Recruitment\Offering\Actions\SendOffer;
use App\Domains\Recruitment\Offering\Actions\UpdateOffer;
use App\Domains\Recruitment\Offering\Exceptions\OfferNotFound;
use App\Domains\Recruitment\Offering\Presenters\OfferPresenter;
use App\Domains\Recruitment\Offering\Support\OfferScope;
use App\Domains\Shared\Support\IdempotencyGuard;
use App\Http\Requests\Offering\CreateOfferRequest;
use App\Http\Requests\Offering\RejectOfferRequest;
use App\Http\Requests\Offering\UpdateOfferRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Offering Foundation v1 (OF-1, OF-2, RC-1, all approved and CLOSED).
 * `COMPANY` vacancies only — `CAMPUS_SCOPE` is not activated. Recruiter-side
 * writes (create/update/send) are `COMPANY_SCOPE`/`SUPER_ADMIN`; candidate
 * responses (accept/reject) are `OWN` only, with **no** proxy capability for
 * any actor including `SUPER_ADMIN` (OF-2, an explicit exception to its
 * otherwise-unconditional recruiter-side `ALLOW`). Offer revoke/withdraw,
 * the expiry scheduler, salary fields, and the document upload mechanism
 * all remain deliberately unimplemented.
 */
final class OfferController extends CandidateController
{
    public function __construct(
        CandidateProfileResolver $profiles,
        private readonly IdempotencyGuard $idempotency,
    ) {
        parent::__construct($profiles);
    }

    public function store(CreateOfferRequest $request, int $application, CreateOffer $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $model = RecruiterApplicationScope::findFor($actor, $application) ?? throw new ApplicationNotFound();

        $offer = $action->execute($actor, $model, $request->validated());

        return ContractResponse::success($request, OfferPresenter::operational($offer), 201);
    }

    public function update(UpdateOfferRequest $request, int $offer, UpdateOffer $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $model = OfferScope::findFor($actor, $offer) ?? throw new OfferNotFound();

        $updated = $action->execute($actor, $model, $request->validated());

        return ContractResponse::success($request, OfferPresenter::operational($updated));
    }

    public function send(Request $request, int $offer, SendOffer $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $model = OfferScope::findFor($actor, $offer) ?? throw new OfferNotFound();

        return $this->idempotent($request, $actor, 'offer.send', $offer, function () use ($actor, $model, $action): array {
            $sent = $action->execute($actor, $model);

            return OfferPresenter::operational($sent);
        }, 200);
    }

    public function accept(Request $request, int $offer, AcceptOffer $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! ApplicationScope::isCandidateActor($actor)) {
            return $this->forbidden($request);
        }

        $profile = $this->ownProfile($request);
        $model = OfferScope::candidateFindFor($profile, $offer) ?? throw new OfferNotFound();

        return $this->idempotent($request, $actor, 'offer.accept', $offer, function () use ($actor, $model, $action): array {
            $accepted = $action->execute($actor, $model);

            return OfferPresenter::candidate($accepted);
        }, 200);
    }

    public function reject(RejectOfferRequest $request, int $offer, RejectOffer $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! ApplicationScope::isCandidateActor($actor)) {
            return $this->forbidden($request);
        }

        $profile = $this->ownProfile($request);
        $model = OfferScope::candidateFindFor($profile, $offer) ?? throw new OfferNotFound();

        return $this->idempotent($request, $actor, 'offer.reject', $offer, function () use ($actor, $model, $request, $action): array {
            $rejected = $action->execute($actor, $model, $request->filled('rejection_reason') ? $request->string('rejection_reason')->toString() : null);

            return OfferPresenter::candidate($rejected);
        }, 200);
    }

    private function isRecruiterActor(User $actor): bool
    {
        return OfferScope::isRecruiterOrAdmin($actor) || OfferScope::isSuperAdmin($actor);
    }

    private function forbidden(Request $request): JsonResponse
    {
        return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
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
