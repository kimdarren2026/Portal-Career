<?php

declare(strict_types=1);

namespace App\Domains\Company\Enums;

enum CompanyReviewAction: string
{
    case Submit = 'SUBMIT';
    case RequestRevision = 'REQUEST_REVISION';
    case Verify = 'VERIFY';
    case Reject = 'REJECT';
    case Suspend = 'SUSPEND';
    case Restore = 'RESTORE';
}
