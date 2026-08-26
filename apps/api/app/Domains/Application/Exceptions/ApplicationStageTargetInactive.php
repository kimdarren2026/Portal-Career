<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/** The requested to_stage_id belongs to the application's vacancy but is not active. */
final class ApplicationStageTargetInactive extends RuntimeException {}
