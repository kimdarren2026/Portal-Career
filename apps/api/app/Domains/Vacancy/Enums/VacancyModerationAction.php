<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Enums;

/** The seven actions `chk_vacancy_moderation_reviews_action` permits. Nothing else may be written. */
enum VacancyModerationAction: string
{
    case Submit = 'SUBMIT';
    case RequestRevision = 'REQUEST_REVISION';
    case Approve = 'APPROVE';
    case Reject = 'REJECT';
    case Suspend = 'SUSPEND';
    case Restore = 'RESTORE';
    case Close = 'CLOSE';

    /** INV-029: these three require a category and a recruiter-visible note. */
    public function requiresReason(): bool
    {
        return in_array($this, [self::RequestRevision, self::Reject, self::Suspend], true);
    }

    /** Frozen audit event names (API_CONTRACT.md). None is invented. */
    public function auditAction(): string
    {
        return match ($this) {
            self::Submit => 'vacancy_submitted',
            self::RequestRevision => 'vacancy_revision_requested',
            self::Approve => 'vacancy_approved',
            self::Reject => 'vacancy_rejected',
            self::Suspend => 'vacancy_suspended',
            self::Restore => 'vacancy_restored',
            self::Close => 'vacancy_closed',
        };
    }

    /** Moderation proper — CLOSE is excluded because an owner may also close (B-3). */
    public function isModeration(): bool
    {
        return in_array(
            $this,
            [self::RequestRevision, self::Approve, self::Reject, self::Suspend, self::Restore],
            true,
        );
    }
}
