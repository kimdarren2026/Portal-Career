<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/** INV-028: audience eligibility failed against candidate_verifications. */
final class CandidateNotEligible extends RuntimeException {}
