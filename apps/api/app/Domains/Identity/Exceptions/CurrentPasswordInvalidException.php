<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

final class CurrentPasswordInvalidException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The current password is not valid.');
    }
}
