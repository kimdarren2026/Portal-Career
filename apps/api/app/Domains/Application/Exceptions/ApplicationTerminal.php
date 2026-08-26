<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/** The application is in a terminal state (REJECTED, WITHDRAWN) and /transition cannot leave it — reactivation is reopen (AD-2, open). */
final class ApplicationTerminal extends RuntimeException {}
