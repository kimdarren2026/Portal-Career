<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/** A required active screening question was not answered. */
final class ApplicationScreeningIncomplete extends RuntimeException {}
