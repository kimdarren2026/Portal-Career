<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Exceptions;

use RuntimeException;

/** The schedule is not currently SCHEDULED, so reschedule/cancel is not legal from its current status. */
final class ScheduleInvalidTransition extends RuntimeException {}
