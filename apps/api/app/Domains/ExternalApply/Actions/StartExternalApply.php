<?php

declare(strict_types=1);

namespace App\Domains\ExternalApply\Actions;

use App\Domains\Application\Exceptions\ApplicationConsentRequired;
use App\Domains\Application\Exceptions\CandidateNotEligible;
use App\Domains\Application\Exceptions\ConsentVersionUnknown;
use App\Domains\Application\Exceptions\VacancyNotOpenForApplication;
use App\Domains\Application\Support\ApplicationConsentVersion;
use App\Domains\Application\Support\ApplicationEligibility;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\ExternalApply\Exceptions\ExternalApplyInvalidMethod;
use App\Domains\ExternalApply\Models\ExternalApplyEvent;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Enums\ApplicationMethod;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * `POST /vacancies/{vacancy}/external-apply/start` (FR-EXT-001, FR-EXT-002).
 *
 * Records that a signed-in candidate is leaving for an external ATS. Enforced
 * structurally against INV-012 / INV-024: this Action has NO path to
 * `applications` and never sets a status to `APPLIED`. The destination URL is
 * read exclusively from the stored vacancy (`external_ats_url`, https-only by
 * `chk_vacancies_external_url`) — a client-supplied URL is never trusted, and
 * the server never fetches, previews, or validates the URL by requesting it
 * (SSRF — SECURITY_ARCHITECTURE.md §4).
 *
 * Consent: the request must carry an accepted consent object citing a known
 * version. No separate `consents` row is written in this milestone — the
 * contract makes it optional ("`consent_id` where tracking consent applies"),
 * and the authoritative external-apply consent text / type vocabulary is not
 * ratified (§14). `consent_id` therefore stays null; the event row itself is
 * the durable record that the acknowledgement was made.
 */
final class StartExternalApply
{
    /** Same Foundation-v1 supported set as in-portal submit; INTERNAL stays deferred. */
    private const SUPPORTED_AUDIENCES = ['PUBLIC', 'ALUMNI_ONLY', 'FINAL_YEAR_AND_ALUMNI'];

    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, mixed> $data */
    public function execute(User $actor, CandidateProfile $profile, int $vacancyId, array $data): ExternalApplyEvent
    {
        return DB::transaction(function () use ($actor, $profile, $vacancyId, $data): ExternalApplyEvent {
            $vacancy = $this->lockEligibleVacancy($vacancyId);

            $this->assertAudienceEligible($vacancy, $profile);
            $this->assertConsent($data['consent'] ?? null);

            $now = now();
            $event = new ExternalApplyEvent();
            $event->forceFill([
                'candidate_profile_id' => $profile->getKey(),
                'vacancy_id' => $vacancy->getKey(),
                'event_type' => ExternalApplyEvent::EVENT_STARTED,
                'destination_url_reference' => (string) $vacancy->external_ats_url,
                'started_at' => $now,
                'confirmation_status' => ExternalApplyEvent::STATUS_PENDING,
                'confirmation_source' => null,
                'confirmed_at' => null,
                'confirmed_by' => null,
                'consent_id' => null,
                'created_at' => $now,
            ])->save();

            $this->audit->record('external_apply_started', $actor, 'external_apply_event', (int) $event->getKey(), [
                'vacancy_id' => (int) $vacancy->getKey(),
            ]);

            return $event->refresh();
        });
    }

    private function lockEligibleVacancy(int $vacancyId): Vacancy
    {
        /** @var Vacancy|null $vacancy */
        $vacancy = Vacancy::query()->whereKey($vacancyId)->lockForUpdate()->first();

        // Nonexistent, unknown ownership, not PUBLISHED, or outside the active
        // window are all the same "not currently applicable" fact — never
        // leaked through a distinct error shape.
        if ($vacancy === null || ! in_array($vacancy->ownership_type, ['COMPANY', 'CAMPUS'], true)) {
            throw new VacancyNotOpenForApplication();
        }

        $now = now();
        if ($vacancy->current_status !== VacancyStatus::Published
            || $vacancy->open_at === null || $now < $vacancy->open_at
            || $vacancy->close_at === null || $now >= $vacancy->close_at) {
            throw new VacancyNotOpenForApplication();
        }

        if ($vacancy->application_method !== ApplicationMethod::ExternalAts) {
            // An in-portal vacancy is applied to through Part VI, not here.
            throw new ExternalApplyInvalidMethod();
        }

        return $vacancy;
    }

    private function assertAudienceEligible(Vacancy $vacancy, CandidateProfile $profile): void
    {
        $audience = $vacancy->target_audience?->value;

        if (! in_array($audience, self::SUPPORTED_AUDIENCES, true)) {
            throw new CandidateNotEligible();
        }

        ApplicationEligibility::assert($profile, $audience);
    }

    private function assertConsent(mixed $consent): void
    {
        if (! is_array($consent) || ($consent['accepted'] ?? null) !== true) {
            throw new ApplicationConsentRequired();
        }

        $version = trim((string) ($consent['consent_version'] ?? ''));
        if (! in_array($version, ApplicationConsentVersion::known(), true)) {
            throw new ConsentVersionUnknown();
        }
    }
}
