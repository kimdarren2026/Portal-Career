<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Evaluation\Exceptions;

use RuntimeException;

/** Author-only restriction: PATCH/submit belong only to the evaluation's own evaluator_user_id, even for SUPER_ADMIN. */
final class EvaluationNotOwned extends RuntimeException {}
