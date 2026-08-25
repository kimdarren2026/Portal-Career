<?php

declare(strict_types=1);

namespace App\Domains\Company\Exceptions;

use RuntimeException;

final class CompanyProfileIncomplete extends RuntimeException
{
    /** @param list<string> $missing */
    public function __construct(public readonly array $missing)
    {
        parent::__construct('COMPANY_PROFILE_INCOMPLETE');
    }
}
