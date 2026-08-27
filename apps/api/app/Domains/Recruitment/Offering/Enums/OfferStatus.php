<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Enums;

/** The frozen offers.status vocabulary (chk_offers_status, FR-SEL-003). PENDING_RESPONSE is a dormant frozen value — no trigger for it exists in this milestone. */
enum OfferStatus: string
{
    case Draft = 'DRAFT';
    case Sent = 'SENT';
    case PendingResponse = 'PENDING_RESPONSE';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
}
