<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Exceptions;

use RuntimeException;

/** On-site requires location; online requires meeting_url. */
final class ScheduleMethodDetailRequired extends RuntimeException {}
