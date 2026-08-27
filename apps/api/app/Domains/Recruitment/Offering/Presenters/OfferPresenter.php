<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Presenters;

use App\Domains\Recruitment\Offering\Models\Offer;

/**
 * Two deliberate allow-lists. `document_reference` is never returned by
 * either — no download path exists for it in Foundation v1 (mirrors RA-3's
 * and Selection Schedule's own "metadata only, never a raw storage
 * reference" precedent).
 */
final class OfferPresenter
{
    /** @return array<string, mixed> */
    public static function operational(Offer $offer): array
    {
        return [
            'id' => (int) $offer->getKey(),
            'application_id' => (int) $offer->application_id,
            'offered_by_user_id' => (int) $offer->offered_by_user_id,
            'status' => $offer->status?->value,
            'offered_at' => $offer->offered_at?->toIso8601String(),
            'response_deadline' => $offer->response_deadline?->toIso8601String(),
            'note' => $offer->note,
            'rejection_reason' => $offer->rejection_reason,
            'sent_at' => $offer->sent_at?->toIso8601String(),
            'responded_at' => $offer->responded_at?->toIso8601String(),
            'offer_accepted_at' => $offer->offer_accepted_at?->toIso8601String(),
            'created_at' => $offer->created_at?->toIso8601String(),
            'updated_at' => $offer->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function candidate(Offer $offer): array
    {
        return [
            'id' => (int) $offer->getKey(),
            'status' => $offer->status?->value,
            'offered_at' => $offer->offered_at?->toIso8601String(),
            'response_deadline' => $offer->response_deadline?->toIso8601String(),
            'note' => $offer->note,
            'sent_at' => $offer->sent_at?->toIso8601String(),
            'responded_at' => $offer->responded_at?->toIso8601String(),
        ];
    }
}
