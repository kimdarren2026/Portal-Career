<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Enums;

/**
 * The campus vacancy lifecycle actions an Admin Kepegawaian may invoke
 * directly (FSD §8.4, FR-HR-004). Campus vacancies are NOT moderated — there
 * is no submit / request-revision / approve / reject.
 */
enum CampusVacancyAction: string
{
    case Publish = 'publish';
    case Schedule = 'schedule';
    case Close = 'close';
    case Suspend = 'suspend';
    case Restore = 'restore';

    public function auditAction(): string
    {
        return match ($this) {
            self::Publish => 'vacancy_published',
            self::Schedule => 'vacancy_scheduled',
            self::Close => 'vacancy_closed',
            self::Suspend => 'vacancy_suspended',
            self::Restore => 'vacancy_restored',
        };
    }
}
