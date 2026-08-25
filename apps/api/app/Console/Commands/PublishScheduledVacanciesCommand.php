<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Vacancy\Actions\PublishScheduledVacancies;
use Illuminate\Console\Command;

/** Publishes SCHEDULED company vacancies whose open_at has been reached (B-4). */
final class PublishScheduledVacanciesCommand extends Command
{
    protected $signature = 'vacancies:publish-scheduled';

    protected $description = 'Publish SCHEDULED company vacancies whose open_at has been reached';

    public function handle(PublishScheduledVacancies $action): int
    {
        $this->info(sprintf('Published %d scheduled vacancies.', $action->execute()));

        return self::SUCCESS;
    }
}
