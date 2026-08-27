<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Support;

use App\Domains\Identity\Models\User;
use App\Domains\Notification\Support\OutboxWriter;
use App\Domains\Recruitment\Offering\Models\Offer;
use Illuminate\Support\Facades\DB;

/**
 * Queues Offering notifications INSIDE the caller's business transaction
 * (INV-015). "Owner" resolves to every active member of the owning company,
 * the same recipient-resolution rule `ApplicationNotifier`/`EvaluationNotifier`
 * already established for FR-NOTIF-002. Send notifies the candidate only
 * (FR-NOTIF-002 "Offering diterbitkan"). Accept notifies both the owner and
 * the candidate — the frozen accept contract explicitly states "Vacancy
 * owner notified... candidate confirmation". Reject notifies the owner
 * **only** — the frozen reject contract states "Vacancy owner notified."
 * with no candidate-confirmation clause, unlike accept; this asymmetry is
 * deliberate in the source, not an oversight.
 */
final class OfferNotifier
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    public function sent(Offer $offer, User $candidateUser): void
    {
        $this->queueOne((int) $candidateUser->getKey(), (string) $candidateUser->email, $offer, 'OFFER_SENT', 'offer.sent.candidate');
    }

    public function accepted(Offer $offer, User $candidateUser, int $companyId): void
    {
        $this->queueOne((int) $candidateUser->getKey(), (string) $candidateUser->email, $offer, 'OFFER_ACCEPTED', 'offer.accepted.candidate');
        foreach ($this->companyMemberEmails($companyId) as $userId => $email) {
            $this->queueOne($userId, (string) $email, $offer, 'OFFER_ACCEPTED', 'offer.accepted.owner');
        }
    }

    public function rejected(Offer $offer, int $companyId): void
    {
        foreach ($this->companyMemberEmails($companyId) as $userId => $email) {
            $this->queueOne($userId, (string) $email, $offer, 'OFFER_REJECTED', 'offer.rejected.owner');
        }
    }

    private function queueOne(int $userId, string $email, Offer $offer, string $type, string $templateReference): void
    {
        $payload = [
            'offer_id' => (int) $offer->getKey(),
            'application_id' => (int) $offer->application_id,
        ];

        $this->outbox->queue($email, $templateReference, $payload, 'offer', (int) $offer->getKey());
        DB::table('notifications')->insert([
            'user_id' => $userId,
            'type' => $type,
            'title' => $type,
            'body_reference' => $templateReference,
            'related_object_type' => 'offer',
            'related_object_id' => $offer->getKey(),
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
