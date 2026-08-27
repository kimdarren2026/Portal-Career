<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Enums;

/** The frozen selection_schedule_histories.event_type vocabulary (chk_selection_schedule_histories_event_type). */
enum SelectionScheduleEventType: string
{
    case Created = 'CREATED';
    case Rescheduled = 'RESCHEDULED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case NoShow = 'NO_SHOW';
}
