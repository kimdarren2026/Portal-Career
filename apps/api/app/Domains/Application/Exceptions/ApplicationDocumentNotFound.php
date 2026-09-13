<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/**
 * The shared application document does not exist, is revoked, or is outside
 * the actor's scope — all indistinguishable to the caller (enumeration-safe,
 * matrix §1). Also raised when the stored object is missing.
 */
final class ApplicationDocumentNotFound extends RuntimeException {}
