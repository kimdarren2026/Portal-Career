<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Recruitment\Offering\Enums\OfferStatus;
use App\Domains\Recruitment\Offering\Exceptions\OfferAlreadyResponded;
use App\Domains\Recruitment\Offering\Exceptions\OfferExpired;
use App\Domains\Recruitment\Offering\Exceptions\OfferNotSent;
use App\Domains\Recruitment\Offering\Models\Offer;
use App\Domains\Recruitment\Offering\Support\OfferNotifier;
use Illuminate\Support\Facades\DB;

/**
 * `POST /offers/{offer}/reject` — Offering Foundation v1 (OF-1, OF-2,
 * approved and CLOSED). Candidate's own act only, RA-2-exempt (RC-1).
 *
 * Only the offer row is locked — reject never touches
 * `applications.current_status` (the frozen contract's own text: "The
 * application does not automatically become REJECTED"), so no application
 * lock is needed and none is taken. A transaction that only ever acquires
 * one resource can never participate in a lock-order inversion with the
 * rest of this domain.
 */
final class RejectOffer
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly OfferNotifier $notifier,
    ) {}

    public function execute(User $actor, Offer $offer, ?string $rejectionReason): Offer
    {
        return DB::transaction(function () use ($actor, $offer, $rejectionReason): Offer {
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

            $locked->forceFill([
                'status' => OfferStatus::Rejected->value,
                'rejection_reason' => $rejectionReason,
                'responded_at' => $now,
                'updated_at' => $now,
            ])->save();

            $this->audit->record('offer_rejected', $actor, 'offer', (int) $locked->getKey(), []);

            $locked->refresh();
            $companyId = (int) DB::table('applications')
                ->join('vacancies', 'vacancies.id', '=', 'applications.vacancy_id')
                ->where('applications.id', $locked->application_id)
                ->value('vacancies.company_id');
            $this->notifier->rejected($locked, $companyId);

            return $locked;
        });
    }
}
