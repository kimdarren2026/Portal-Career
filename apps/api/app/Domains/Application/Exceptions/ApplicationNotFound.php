<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/** Out of OWN scope and absent answer identically: a 403 would confirm the row exists. */
final class ApplicationNotFound extends RuntimeException {}
