<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Enums;

enum ApplicationMethod: string
{
    case InPortal = 'IN_PORTAL';
    case ExternalAts = 'EXTERNAL_ATS';
}
