<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Enums;

/**
 * The ten persisted vacancy statuses (FSD §8.3, §8.4 · INV-004).
 *
 * `SUBMITTED`/`DIAJUKAN` deliberately do not exist: submit is an action, never
 * a stored status (FR-VAC-004).
 */
enum VacancyStatus: string
{
    case Draft = 'DRAFT';
    case PendingReview = 'PENDING_REVIEW';
    case RevisionRequired = 'REVISION_REQUIRED';
    case Approved = 'APPROVED';
    case Scheduled = 'SCHEDULED';
    case Published = 'PUBLISHED';
    case Rejected = 'REJECTED';
    case Closed = 'CLOSED';
    case Expired = 'EXPIRED';
    case Suspended = 'SUSPENDED';

    /** Company vacancies are editable in DRAFT and REVISION_REQUIRED only (PATCH contract). */
    public function isCompanyEditable(): bool
    {
        return $this === self::Draft || $this === self::RevisionRequired;
    }
}
