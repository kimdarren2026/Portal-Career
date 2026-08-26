<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use App\Domains\Application\Enums\ApplicationStatus;
use App\Domains\Application\Exceptions\ApplicationInvalidTransition;
use App\Domains\Application\Exceptions\ApplicationTerminal;

/**
 * RA-1, approved and CLOSED: Recruiter Applicant Management Foundation v1
 * supports exactly five edges. Every other target — including every later-phase
 * status (ASSESSMENT, INTERVIEW, OFFERED, HIRED, NO_SHOW), every backward edge,
 * and same-status no-ops — is rejected. WITHDRAWN is never a /transition target
 * (candidate-only action). REJECTED and WITHDRAWN are terminal for this
 * milestone; leaving either is `reopen`, which AD-2 leaves open.
 */
final class ApplicationTransitionGraph
{
    /** @var array<string, list<string>> */
    private const EDGES = [
        'APPLIED' => ['UNDER_REVIEW', 'REJECTED'],
        'UNDER_REVIEW' => ['SHORTLISTED', 'REJECTED'],
        'SHORTLISTED' => ['REJECTED'],
    ];

    private const TERMINAL = ['REJECTED', 'WITHDRAWN'];

    /** @throws ApplicationTerminal|ApplicationInvalidTransition */
    public static function assertLegal(ApplicationStatus $from, ApplicationStatus $to): void
    {
        if (in_array($from->value, self::TERMINAL, true)) {
            throw new ApplicationTerminal();
        }

        $allowed = self::EDGES[$from->value] ?? [];
        if (! in_array($to->value, $allowed, true)) {
            throw new ApplicationInvalidTransition($from->value, $to->value);
        }
    }
}
