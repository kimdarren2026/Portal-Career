<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

final class PasswordPolicyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Password belum memenuhi kebijakan keamanan.');
    }
}
