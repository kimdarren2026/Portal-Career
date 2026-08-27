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
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * `PATCH /offers/{offer}` — Offering Foundation v1 (OF-1, RC-1, all
 * approved and CLOSED). `DRAFT` only. Mutable fields: `response_deadline`,
 * `note`. `document_reference` is never accepted (see the create contract's
 * amendment note); `application_id`/`offered_by_user_id` are never mutable.
 *
 * Lock order: application → offer → vacancy → company — application is
 * locked first even though this Action's own logic does not strictly need
 * it, specifically to stay consistent with `AcceptOffer`'s application-first
 * order (required there for INV-031 correctness) and avoid any inversion.
 */
final class UpdateOffer
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, Offer $offer, array $attributes): Offer
    {
        return DB::transaction(function () use ($actor, $offer, $attributes): Offer {
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

            $locked->forceFill([
                'response_deadline' => array_key_exists('response_deadline', $attributes) ? $attributes['response_deadline'] : $locked->response_deadline,
                'note' => array_key_exists('note', $attributes) ? $attributes['note'] : $locked->note,
                'updated_at' => now(),
            ])->save();

            $this->audit->record('offer_updated', $actor, 'offer', (int) $locked->getKey(), []);

            return $locked->refresh();
        });
    }
}
