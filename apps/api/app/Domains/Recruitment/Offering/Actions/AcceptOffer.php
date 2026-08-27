<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Actions;

use App\Domains\Application\Enums\ApplicationEventType;
use App\Domains\Application\Enums\ApplicationStatus;
use App\Domains\Application\Models\Application;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Recruitment\Offering\Enums\OfferStatus;
use App\Domains\Recruitment\Offering\Exceptions\OfferAlreadyAcceptedForApplication;
use App\Domains\Recruitment\Offering\Exceptions\OfferAlreadyResponded;
use App\Domains\Recruitment\Offering\Exceptions\OfferExpired;
use App\Domains\Recruitment\Offering\Exceptions\OfferNotSent;
use App\Domains\Recruitment\Offering\Models\Offer;
use App\Domains\Recruitment\Offering\Support\OfferNotifier;
use Illuminate\Support\Facades\DB;

/**
 * `POST /offers/{offer}/accept` — Offering Foundation v1 (OF-1, OF-2,
 * approved and CLOSED). Candidate's own act only, RA-2-exempt (RC-1).
 *
 * Application locked FIRST — required for INV-031 correctness: two
 * concurrent accepts of DIFFERENT offers on the SAME application must
 * serialize through the application row, not just their own offer rows.
 * The offer is locked second. This is the same relative order every other
 * writer in this domain uses (`CreateOffer`, `UpdateOffer`, `SendOffer`),
 * so accept can never invert against them.
 *
 * The already-frozen atomic side effect is preserved exactly (OF-1 does not
 * remove it): `applications.current_status = HIRED`, `hired_at` set, and an
 * `OFFER_ACCEPTED` history event — all in the same transaction as the offer
 * mutation (INV-026).
 */
final class AcceptOffer
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly OfferNotifier $notifier,
    ) {}

    public function execute(User $actor, Offer $offer): Offer
    {
        return DB::transaction(function () use ($actor, $offer): Offer {
            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()->whereKey($offer->application_id)->lockForUpdate()->firstOrFail();

            /** @var Offer $locked */
            $locked = Offer::query()->whereKey($offer->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === OfferStatus::Draft) {
                throw new OfferNotSent();
            }
            if (in_array($locked->status, [OfferStatus::Accepted, OfferStatus::Rejected], true)) {
                throw new OfferAlreadyResponded();
            }
            if ($locked->status === OfferStatus::Expired) {
                throw new OfferExpired();
            }
            $now = now();
            if ($locked->response_deadline !== null && $locked->response_deadline->lessThan($now)) {
                throw new OfferExpired();
            }

            $hasOtherAccepted = Offer::query()->where('application_id', $lockedApplication->getKey())
                ->where('status', OfferStatus::Accepted->value)
                ->whereKeyNot($locked->getKey())->exists();
            if ($hasOtherAccepted) {
                throw new OfferAlreadyAcceptedForApplication();
            }

            $locked->forceFill([
                'status' => OfferStatus::Accepted->value,
                'responded_at' => $now,
                'offer_accepted_at' => $now,
                'updated_at' => $now,
            ])->save();

            $fromStatus = $lockedApplication->current_status;
            $lockedApplication->current_status = ApplicationStatus::Hired;
            $lockedApplication->hired_at = $now;
            $lockedApplication->updated_at = $now;
            $lockedApplication->save();

            $lockedApplication->statusHistories()->forceCreate([
                'from_status' => $fromStatus?->value,
                'to_status' => ApplicationStatus::Hired->value,
                'event_type' => ApplicationEventType::OfferAccepted->value,
                'actor_user_id' => $actor->getKey(),
                'reason' => null,
                'candidate_visibility' => 'VISIBLE',
                'occurred_at' => $now,
            ]);

            $this->audit->record('offer_accepted', $actor, 'offer', (int) $locked->getKey(), [
                'application_id' => (int) $lockedApplication->getKey(),
                'offer_accepted_at' => $now->toIso8601String(),
            ]);

            $locked->refresh();
            $companyId = (int) $lockedApplication->vacancy()->value('company_id');
            $this->notifier->accepted($locked, $actor, $companyId);

            return $locked;
        });
    }
}
