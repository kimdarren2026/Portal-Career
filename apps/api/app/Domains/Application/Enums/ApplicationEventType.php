<?php

declare(strict_types=1);

namespace App\Domains\Application\Enums;

/** The frozen application_status_histories.event_type vocabulary (chk_application_status_histories_event_type). */
enum ApplicationEventType: string
{
    case ApplicationCreated = 'APPLICATION_CREATED';
    case StatusChanged = 'STATUS_CHANGED';
    case StageChanged = 'STAGE_CHANGED';
    case ApplicationReopened = 'APPLICATION_REOPENED';
    case Withdrawn = 'WITHDRAWN';
    case Rejected = 'REJECTED';
    case OfferAccepted = 'OFFER_ACCEPTED';
    case NoShow = 'NO_SHOW';
}
