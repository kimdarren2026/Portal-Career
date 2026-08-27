<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Exceptions;

use RuntimeException;

/** The requested offer does not exist, or exists outside the actor's scope (enumeration-safe). */
final class OfferNotFound extends RuntimeException {}
