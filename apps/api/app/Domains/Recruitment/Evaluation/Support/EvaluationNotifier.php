<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Evaluation\Support;

use App\Domains\Notification\Support\OutboxWriter;
use App\Domains\Recruitment\Evaluation\Models\Evaluation;
use Illuminate\Support\Facades\DB;

/**
 * Queues the submission notification INSIDE the caller's business
 * transaction (INV-015). "Owner notified on submission. Never the
 * candidate" (frozen contract) — "owner" resolves to every active member of
 * the owning company, the same recipient-resolution rule `ApplicationNotifier`
 * already established for FR-NOTIF-002 ("Kandidat dan owner lowongan"),
 * reused here for the owner half only. No candidate notification exists
 * for any Evaluation operation.
 */
final class EvaluationNotifier
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    public function submitted(Evaluation $evaluation, int $companyId): void
    {
        $payload = [
            'evaluation_id' => (int) $evaluation->getKey(),
            'application_id' => (int) $evaluation->application_id,
            'recruitment_stage_id' => (int) $evaluation->recruitment_stage_id,
        ];

        foreach ($this->companyMemberEmails($companyId) as $userId => $email) {
            $this->outbox->queue((string) $email, 'evaluation.submitted.owner', $payload, 'evaluation', (int) $evaluation->getKey());
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'type' => 'EVALUATION_SUBMITTED',
                'title' => 'EVALUATION_SUBMITTED',
                'body_reference' => 'evaluation.submitted.owner',
                'related_object_type' => 'evaluation',
                'related_object_id' => $evaluation->getKey(),
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
