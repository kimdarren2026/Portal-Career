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
 * Recipients are the candidate **and** the vacancy owner — for a COMPANY
 * vacancy, every active member of the owning company. This is governed by
 * FSD FR-NOTIF-002 ("Application berhasil -> Kandidat dan owner lowongan")
 * and `API_CONTRACT.md`'s submit/withdraw sections, which name both parties
 * explicitly. `VacancyLifecycleNotifier`'s owner-only recipient set answers a
 * different FR-NOTIF-002 trigger row (vacancy lifecycle, where the recruiter
 * is the actor) and is not authoritative for Application recipients.
 */
final class ApplicationNotifier
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    public function submitted(Application $application, User $candidateUser, int $companyId): void
    {
        $this->queueBoth($application, $candidateUser, $companyId, 'APPLICATION_SUBMITTED', 'application.submitted');
    }

    public function withdrawn(Application $application, User $candidateUser, int $companyId): void
    {
        $this->queueBoth($application, $candidateUser, $companyId, 'APPLICATION_WITHDRAWN', 'application.withdrawn');
    }

    private function queueBoth(Application $application, User $candidateUser, int $companyId, string $type, string $templatePrefix): void
    {
        $payload = [
            'application_id' => (int) $application->getKey(),
            'application_code' => $application->application_code,
            'vacancy_id' => (int) $application->vacancy_id,
            'current_status' => $application->current_status?->value,
        ];

        $this->queueOne((string) $candidateUser->email, (int) $candidateUser->getKey(), $application, $type, $templatePrefix.'.candidate', $payload);

        foreach ($this->companyMemberEmails($companyId) as $userId => $email) {
            $this->queueOne((string) $email, (int) $userId, $application, $type, $templatePrefix.'.owner', $payload);
        }
    }

    /** @param array<string, mixed> $payload */
    private function queueOne(string $email, int $userId, Application $application, string $type, string $bodyReference, array $payload): void
    {
        $this->outbox->queue($email, $bodyReference, $payload, 'application', (int) $application->getKey());
        DB::table('notifications')->insert([
            'user_id' => $userId,
            'type' => $type,
            'title' => $type,
            'body_reference' => $bodyReference,
            'related_object_type' => 'application',
            'related_object_id' => $application->getKey(),
            'created_at' => now(),
        ]);
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
