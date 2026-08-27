<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Exceptions;

use RuntimeException;

/** SS-5: starts_at not strictly future, or ends_at not strictly after starts_at. */
final class ScheduleTimeInvalid extends RuntimeException {}
