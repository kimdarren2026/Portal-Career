<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/** INV-011: consent and application are inseparable — missing or unaccepted consent. */
final class ApplicationConsentRequired extends RuntimeException {}
