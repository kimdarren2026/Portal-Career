<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Notification\Support\OutboxWriter;
use App\Domains\Vacancy\Enums\VacancyModerationAction;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * Queues lifecycle notifications INSIDE the caller's business transaction
 * (INV-015). No SMTP is performed here and no delivery is attempted; the
 * worker sends after commit.
 *
 * Recipients are exactly the frozen ones and no group is invented:
 *  - every lifecycle event notifies the vacancy owner, i.e. the active members
 *    of the owning company (FR-NOTIF-002, "recruiter/owner notification");
 *  - SUBMIT additionally notifies the Career Center moderation queue, which is
 *    the only extra recipient any of these contracts names.
 *
 * The payload carries the recruiter-visible note only. `internal_note` is never
 * queued, never rendered, and never leaves the moderation history.
 */
final class VacancyLifecycleNotifier
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    /** @param array<string, mixed> $details */
    public function queue(Vacancy $vacancy, VacancyModerationAction|string $event, array $details = []): void
    {
        $name = $event instanceof VacancyModerationAction ? $event->value : $event;
        $payload = [
            'vacancy_id' => (int) $vacancy->getKey(),
            'event' => $name,
            'status' => $vacancy->current_status?->value,
        ];
        if (isset($details['recruiter_visible_note'])) {
            $payload['recruiter_visible_note'] = (string) $details['recruiter_visible_note'];
        }

        $owners = $this->companyMemberEmails((int) $vacancy->company_id);
        foreach ($owners as $userId => $email) {
            $this->outbox->queue((string) $email, 'vacancy.lifecycle.'.mb_strtolower($name), $payload, 'vacancy', (int) $vacancy->getKey());
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'type' => 'VACANCY_LIFECYCLE',
                'title' => $name,
                'body_reference' => $name,
                'related_object_type' => 'vacancy',
                'related_object_id' => $vacancy->getKey(),
                'created_at' => now(),
            ]);
        }

        if ($name === VacancyModerationAction::Submit->value) {
            foreach ($this->careerCenterEmails() as $email) {
                $this->outbox->queue((string) $email, 'vacancy.moderation.queue', $payload, 'vacancy', (int) $vacancy->getKey());
            }
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

    /** @return \Illuminate\Support\Collection<int, string> */
    private function careerCenterEmails()
    {
        return DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->whereIn('roles.code', ['CAREER_CENTER_STAFF', 'CAREER_CENTER_MANAGER'])
            ->whereNull('user_roles.revoked_at')
            ->pluck('users.email', 'users.id');
    }
}
