<?php

declare(strict_types=1);

namespace App\Domains\Company\Enums;

enum CompanyStatus: string
{
    case Draft = 'DRAFT';
    case PendingVerification = 'PENDING_VERIFICATION';
    case RevisionRequired = 'REVISION_REQUIRED';
    case Verified = 'VERIFIED';
    case Rejected = 'REJECTED';
    case Suspended = 'SUSPENDED';
}
