<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Evaluation\Exceptions;

use RuntimeException;

/** The requested evaluation does not exist, or exists outside the actor's scope (enumeration-safe). */
final class EvaluationNotFound extends RuntimeException {}
