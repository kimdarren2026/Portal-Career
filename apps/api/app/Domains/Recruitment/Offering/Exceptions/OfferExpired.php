<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Exceptions;

use RuntimeException;

/** Past response_deadline, or status already EXPIRED. */
final class OfferExpired extends RuntimeException {}
