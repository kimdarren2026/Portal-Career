<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/** MS-3, approved and CLOSED: to_stage_id equals the application's current_stage_id. */
final class ApplicationStageAlreadyCurrent extends RuntimeException {}
