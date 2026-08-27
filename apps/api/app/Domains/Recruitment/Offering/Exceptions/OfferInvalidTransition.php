<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Exceptions;

use RuntimeException;

/** The offer is not in the required status for this action (e.g. PATCH/send require DRAFT). */
final class OfferInvalidTransition extends RuntimeException {}
