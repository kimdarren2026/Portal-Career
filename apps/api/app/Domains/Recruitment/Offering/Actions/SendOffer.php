<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Actions;

use App\Domains\Application\Models\Application;
use App\Domains\Application\Support\ApplicationProcessingGate;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Recruitment\Offering\Enums\OfferStatus;
use App\Domains\Recruitment\Offering\Exceptions\OfferInvalidTransition;
use App\Domains\Recruitment\Offering\Models\Offer;
use App\Domains\Recruitment\Offering\Support\OfferNotifier;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * `POST /offers/{offer}/send` — Offering Foundation v1 (OF-1, RC-1, all
 * approved and CLOSED). `DRAFT → SENT` only.
 *
 * Lock order: application → offer → vacancy → company, matching
 * `UpdateOffer`. Never mutates `applications.current_status` to `OFFERED`
 * (OF-1) — the offer lifecycle is represented entirely by `offers.status`.
 */
final class SendOffer
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly OfferNotifier $notifier,
    ) {}

    public function execute(User $actor, Offer $offer): Offer
    {
        return DB::transaction(function () use ($actor, $offer): Offer {
            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()->whereKey($offer->application_id)->lockForUpdate()->firstOrFail();

            /** @var Offer $locked */
            $locked = Offer::query()->whereKey($offer->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OfferStatus::Draft) {
                throw new OfferInvalidTransition();
            }

            /** @var Vacancy $vacancy */
            $vacancy = Vacancy::query()->whereKey($lockedApplication->vacancy_id)->lockForUpdate()->firstOrFail();

            /** @var Company $company */
            $company = Company::query()->whereKey($vacancy->company_id)->lockForUpdate()->firstOrFail();

            ApplicationProcessingGate::assertProcessable($company, $vacancy);

            $now = now();
            $locked->status = OfferStatus::Sent->value;
            $locked->sent_at = $now;
            $locked->updated_at = $now;
            $locked->save();

            $this->audit->record('offer_sent', $actor, 'offer', (int) $locked->getKey(), []);

            $locked->refresh();
            $candidateUser = $lockedApplication->candidateProfile?->user;
            if ($candidateUser !== null) {
                $this->notifier->sent($locked, $candidateUser);
            }

            return $locked;
        });
    }
}
