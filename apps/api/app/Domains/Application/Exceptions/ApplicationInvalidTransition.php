<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/** RA-1: the requested to_status is not a legal edge from the application's current status in this milestone. */
final class ApplicationInvalidTransition extends RuntimeException
{
    public function __construct(public readonly string $from, public readonly string $attempted)
    {
        parent::__construct();
    }
}
