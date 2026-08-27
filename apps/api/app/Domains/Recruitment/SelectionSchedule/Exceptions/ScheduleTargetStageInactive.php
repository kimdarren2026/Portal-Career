<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Exceptions;

use RuntimeException;

/** SS-3: the target (create) or referenced (reschedule) recruitment stage is not active. */
final class ScheduleTargetStageInactive extends RuntimeException {}
