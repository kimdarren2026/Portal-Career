<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Exceptions;

use RuntimeException;

/** If-Match did not match the schedule's authoritative locked revision_number. */
final class SelectionScheduleStaleVersion extends RuntimeException {}
