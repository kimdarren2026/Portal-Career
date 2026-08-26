<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use App\Domains\Application\Models\Application;
use App\Domains\Identity\Models\User;
use App\Domains\Notification\Support\OutboxWriter;
use Illuminate\Support\Facades\DB;

/**
 * Queues application-lifecycle notifications INSIDE the caller's business
 * transaction (INV-015). No SMTP is performed here; the worker sends after
 * commit, so SMTP failure never rolls back the application (FR-NOTIF-002).
 *
 * Recipients are exactly the vacancy owner — for a COMPANY vacancy, every
 * active member of the owning company — the same recipient-set rule already
 * established by `VacancyLifecycleNotifier`. The candidate is the actor
 * performing the action, not a separate recipient; self-notifying the actor
 * is not part of that established contract and is not invented here.
 */
final class ApplicationNotifier
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    public function submitted(Application $application, User $candidateUser, int $companyId): void
    {
        $this->queueOwner($application, $companyId, 'APPLICATION_SUBMITTED', 'application.submitted');
    }

    public function withdrawn(Application $application, User $candidateUser, int $companyId): void
    {
        $this->queueOwner($application, $companyId, 'APPLICATION_WITHDRAWN', 'application.withdrawn');
    }

    private function queueOwner(Application $application, int $companyId, string $type, string $templatePrefix): void
    {
        $payload = [
            'application_id' => (int) $application->getKey(),
            'application_code' => $application->application_code,
            'vacancy_id' => (int) $application->vacancy_id,
            'current_status' => $application->current_status?->value,
        ];

        foreach ($this->companyMemberEmails($companyId) as $userId => $email) {
            $this->outbox->queue((string) $email, $templatePrefix.'.owner', $payload, 'application', (int) $application->getKey());
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'type' => $type,
                'title' => $type,
                'body_reference' => $templatePrefix.'.owner',
                'related_object_type' => 'application',
                'related_object_id' => $application->getKey(),
                'created_at' => now(),
            ]);
        }
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function companyMemberEmails(int $companyId)
    {
        return DB::table('company_members')->join('users', 'users.id', '=', 'company_members.user_id')
            ->where('company_members.company_id', $companyId)
            ->where('company_members.status', 'ACTIVE')
            ->whereNull('company_members.revoked_at')
            ->pluck('users.email', 'users.id');
    }
}
