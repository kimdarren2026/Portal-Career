<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Exceptions;

use RuntimeException;

/** The offer is already ACCEPTED or REJECTED. */
final class OfferAlreadyResponded extends RuntimeException {}
