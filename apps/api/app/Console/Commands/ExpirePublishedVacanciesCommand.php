<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Vacancy\Actions\ExpirePublishedVacancies;
use Illuminate\Console\Command;

/** Expires PUBLISHED company vacancies whose close_at has been reached (O-7). */
final class ExpirePublishedVacanciesCommand extends Command
{
    protected $signature = 'vacancies:expire';

    protected $description = 'Expire PUBLISHED company vacancies whose close_at has been reached';

    public function handle(ExpirePublishedVacancies $action): int
    {
        $this->info(sprintf('Expired %d vacancies.', $action->execute()));

        return self::SUCCESS;
    }
}
