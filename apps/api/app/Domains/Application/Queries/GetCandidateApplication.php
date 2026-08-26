<?php

declare(strict_types=1);

namespace App\Domains\Application\Queries;

use App\Domains\Application\Models\Application;
use App\Domains\Application\Support\ApplicationPresenter;

/**
 * `GET /applications/{application}` detail. Foundation-safe includes:
 * `history`, `documents`, `screening_answers`. `schedules`, `offers`, and
 * `evaluations` are frozen future includes (FSD future domains) — they are
 * simply absent from the allow-list until those domains exist, never
 * fabricated with empty placeholder data.
 */
final class GetCandidateApplication
{
    public const INCLUDES = ['history', 'documents', 'screening_answers'];

    /** @param list<string> $include @return array<string, mixed> */
    public function execute(Application $application, array $include = []): array
    {
        return ApplicationPresenter::detail($application, $include);
    }
}
