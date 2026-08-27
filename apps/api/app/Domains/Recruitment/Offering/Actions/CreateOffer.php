<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Actions;

use App\Domains\Application\Exceptions\ApplicationTerminal;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Support\ApplicationProcessingGate;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Recruitment\Offering\Enums\OfferStatus;
use App\Domains\Recruitment\Offering\Exceptions\OfferAlreadyAcceptedForApplication;
use App\Domains\Recruitment\Offering\Models\Offer;
use App\Domains\Recruitment\Offering\Support\OfferNotifier;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * `POST /applications/{application}/offers` — Offering Foundation v1
 * (OF-1, RC-1, all approved and CLOSED).
 *
 * Lock order: application → vacancy → company. No existing offer row is
 * locked here (a new one is inserted last), so this can never deadlock
 * against `UpdateOffer`/`SendOffer`/`AcceptOffer`, all of which lock
 * application first too (the deterministic order this whole domain uses,
 * chosen for INV-031 correctness in `AcceptOffer`).
 *
 * Status-independent by design (OF-1): `applications.current_status` and
 * `current_stage_id` are never mutated here, even when `send_now` is true.
 */
final class CreateOffer
{
    private const TERMINAL_STATUSES = ['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'];

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly OfferNotifier $notifier,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, Application $application, array $attributes): Offer
    {
        return DB::transaction(function () use ($actor, $application, $attributes): Offer {
            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($lockedApplication->current_status?->value, self::TERMINAL_STATUSES, true)) {
                throw new ApplicationTerminal();
            }

            $hasAccepted = Offer::query()->where('application_id', $lockedApplication->getKey())
                ->where('status', OfferStatus::Accepted->value)->exists();
            if ($hasAccepted) {
                throw new OfferAlreadyAcceptedForApplication();
            }

            /** @var Vacancy $vacancy */
            $vacancy = Vacancy::query()->whereKey($lockedApplication->vacancy_id)->lockForUpdate()->firstOrFail();

            /** @var Company $company */
            $company = Company::query()->whereKey($vacancy->company_id)->lockForUpdate()->firstOrFail();

            ApplicationProcessingGate::assertProcessable($company, $vacancy);

            $sendNow = (bool) ($attributes['send_now'] ?? false);
            $now = now();

            $offer = new Offer();
            $offer->forceFill([
                'application_id' => $lockedApplication->getKey(),
                'offered_by_user_id' => $actor->getKey(),
                'offered_at' => $now,
                'response_deadline' => $attributes['response_deadline'] ?? null,
                'note' => $attributes['note'] ?? null,
                'rejection_reason' => null,
                'document_reference' => null,
                'status' => $sendNow ? OfferStatus::Sent->value : OfferStatus::Draft->value,
                'sent_at' => $sendNow ? $now : null,
                'responded_at' => null,
                'offer_accepted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->save();

            $this->audit->record('offer_created', $actor, 'offer', (int) $offer->getKey(), [
                'application_id' => (int) $lockedApplication->getKey(),
            ]);

            if ($sendNow) {
                $this->audit->record('offer_sent', $actor, 'offer', (int) $offer->getKey(), []);

                $candidateUser = $lockedApplication->candidateProfile?->user;
                if ($candidateUser !== null) {
                    $this->notifier->sent($offer->refresh(), $candidateUser);
                }
            }

            return $offer->refresh();
        });
    }
}
