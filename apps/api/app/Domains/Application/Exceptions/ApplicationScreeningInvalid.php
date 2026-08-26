<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/** An answer's type/choice does not match its question definition, or the question does not belong to this vacancy (INV-019). */
final class ApplicationScreeningInvalid extends RuntimeException {}
