<?php

declare(strict_types=1);

namespace App\Domains\Application\Queries;

use App\Domains\Application\Models\Application;
use App\Domains\Application\Support\RecruiterApplicationPresenter;

/** `GET /applications/{application}` recruiter/company/Super Admin detail. */
final class GetCompanyApplication
{
    /** @return array<string, mixed> */
    public function execute(Application $application): array
    {
        return RecruiterApplicationPresenter::detail($application);
    }
}
