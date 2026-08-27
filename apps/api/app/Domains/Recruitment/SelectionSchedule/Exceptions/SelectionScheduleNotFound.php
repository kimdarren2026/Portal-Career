<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Exceptions;

use RuntimeException;

/** The requested schedule does not exist, or exists outside the actor's scope (enumeration-safe). */
final class SelectionScheduleNotFound extends RuntimeException {}
