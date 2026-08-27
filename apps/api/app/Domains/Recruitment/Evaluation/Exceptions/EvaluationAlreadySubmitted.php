<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Evaluation\Exceptions;

use RuntimeException;

/** The evaluation is already finalized (submitted_at is set) and no longer editable or re-submittable. */
final class EvaluationAlreadySubmitted extends RuntimeException {}
