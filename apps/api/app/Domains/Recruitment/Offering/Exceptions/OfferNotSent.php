<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Exceptions;

use RuntimeException;

/** A DRAFT offer cannot be accepted or rejected. */
final class OfferNotSent extends RuntimeException {}
