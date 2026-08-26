<?php

declare(strict_types=1);

namespace App\Domains\Application\Enums;

/** The ten frozen candidate-facing lifecycle statuses (FR-APP-004, chk_applications_current_status). */
enum ApplicationStatus: string
{
    case Applied = 'APPLIED';
    case UnderReview = 'UNDER_REVIEW';
    case Shortlisted = 'SHORTLISTED';
    case Assessment = 'ASSESSMENT';
    case Interview = 'INTERVIEW';
    case Offered = 'OFFERED';
    case Hired = 'HIRED';
    case Rejected = 'REJECTED';
    case Withdrawn = 'WITHDRAWN';
    case NoShow = 'NO_SHOW';
}
