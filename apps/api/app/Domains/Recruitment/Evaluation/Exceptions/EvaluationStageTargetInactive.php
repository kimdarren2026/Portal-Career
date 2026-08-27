<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Evaluation\Exceptions;

use RuntimeException;

/** EV-2: the target (create) or newly-selected (update) recruitment stage is not active. Submit is exempt. */
final class EvaluationStageTargetInactive extends RuntimeException {}
