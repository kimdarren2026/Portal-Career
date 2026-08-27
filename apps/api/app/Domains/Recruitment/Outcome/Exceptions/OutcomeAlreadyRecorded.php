<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Outcome\Exceptions;

use RuntimeException;

/** One outcome per source (`uq_recruitment_outcomes_application`). */
final class OutcomeAlreadyRecorded extends RuntimeException {}
