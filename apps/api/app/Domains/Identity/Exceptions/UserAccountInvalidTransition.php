<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

/** Suspend requires a currently ACTIVE account; restore requires SUSPENDED (PGC-V1 / PD-F). */
final class UserAccountInvalidTransition extends RuntimeException {}
