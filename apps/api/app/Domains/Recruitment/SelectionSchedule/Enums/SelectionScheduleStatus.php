<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Enums;

/** The frozen selection_schedules.status vocabulary (chk_selection_schedules_status). RESCHEDULED is never stored (INV-027). */
enum SelectionScheduleStatus: string
{
    case Scheduled = 'SCHEDULED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case NoShow = 'NO_SHOW';
}
