<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Exceptions;

use RuntimeException;

/** INV-031: another offer on this application is already ACCEPTED. */
final class OfferAlreadyAcceptedForApplication extends RuntimeException {}
