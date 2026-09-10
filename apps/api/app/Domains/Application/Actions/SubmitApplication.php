<?php

declare(strict_types=1);

namespace App\Domains\Application\Actions;

use App\Domains\Application\Enums\ApplicationEventType;
use App\Domains\Application\Enums\ApplicationStatus;
use App\Domains\Application\Exceptions\ApplicationAlreadyExists;
use App\Domains\Application\Exceptions\ApplicationConsentRequired;
use App\Domains\Application\Exceptions\ApplicationNotInPortalVacancy;
use App\Domains\Application\Exceptions\ApplicationScreeningIncomplete;
use App\Domains\Application\Exceptions\ApplicationScreeningInvalid;
use App\Domains\Application\Exceptions\CandidateNotEligible;
use App\Domains\Application\Exceptions\ConsentVersionUnknown;
use App\Domains\Application\Exceptions\VacancyNotOpenForApplication;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Support\ApplicationConsentVersion;
use App\Domains\Application\Support\ApplicationEligibility;
use App\Domains\Application\Support\ApplicationIdentifier;
use App\Domains\Application\Support\ApplicationNotifier;
use App\Domains\Candidate\Exceptions\CandidateDocumentNotOwnedException;
use App\Domains\Application\Exceptions\ApplicationDocumentArchived;
use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Models\Company;
use App\Domains\Consent\Models\Consent;
use App\Domains\Consent\Support\ConsentReceiver;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Enums\ApplicationMethod as VacancyApplicationMethod;
use App\Domains\Vacancy\Enums\ScreeningQuestionType;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Exceptions\VacancyCompanyNotVerified;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Models\VacancyScreeningQuestion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * `POST /vacancies/{vacancy}/applications` — Foundation v1. The single most
 * invariant-dense operation in the system, validated atomically in one
 * transaction with the vacancy row (and, per AD-1, the company row) locked.
 *
 * Lock order is deterministic: vacancy row first, then the owning company row
 * — the same order `ModerateVacancy::execute()` already uses for its own
 * VERIFIED recheck on APPROVE, so no new lock-ordering convention is
 * introduced. `candidate_profile_id + vacancy_id` uniqueness is never
 * "check then insert": a pre-check gives a clean, immediate 409 for the
 * common case, and the database's own unique constraint
 * (`uq_applications_candidate_vacancy`) is the final race authority — a
 * concurrent loser is caught and mapped to the identical contract error, never
 * a raw SQLSTATE.
 */
final class SubmitApplication
{
    /** Foundation v1 supported audiences (AD-3: INTERNAL remains OPEN and unsupported). */
    private const SUPPORTED_AUDIENCES = ['PUBLIC', 'ALUMNI_ONLY', 'FINAL_YEAR_AND_ALUMNI'];

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly ApplicationNotifier $notifier,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(User $actor, CandidateProfile $profile, int $vacancyId, array $data): Application
    {
        return DB::transaction(function () use ($actor, $profile, $vacancyId, $data): Application {
            $vacancy = $this->lockEligibleVacancy($vacancyId);
            // AD-1 (company VERIFIED gate) applies to company vacancies only.
            // A campus vacancy has no company (FR-HR-001) — CAMPUS_SCOPE was
            // activated by the approved PO / SPEC-DOC decision.
            $company = $vacancy->ownership_type === 'COMPANY'
                ? $this->lockVerifiedCompany($vacancy)
                : null;

            $this->assertAudienceEligible($vacancy, $profile);
            $this->assertNoExistingLifecycle($profile, $vacancy);

            ConsentReceiver::assertClientSuppliedNone($data['receiving_company_id'] ?? null, $data['receiving_organizational_unit_id'] ?? null);

            $consent = $this->validateConsent($data['consent'] ?? null);
            $documents = $this->validateDocuments($data['document_ids'] ?? [], $profile);
            $answers = $this->validateScreeningAnswers($vacancy, $data['screening_answers'] ?? []);

            $now = now();
            $application = $this->insertApplication($profile, $vacancy, $now);

            $receiver = ConsentReceiver::forVacancy($vacancy);
            $consentRow = new Consent();
            $consentRow->forceFill([
                'user_id' => $actor->getKey(),
                'application_id' => $application->getKey(),
                'vacancy_id' => $vacancy->getKey(),
                'receiving_company_id' => $receiver['receiving_company_id'],
                'receiving_organizational_unit_id' => $receiver['receiving_organizational_unit_id'],
                'consent_type' => 'APPLICATION_SHARING',
                'consent_version' => $consent['consent_version'],
                'consent_text_hash_reference' => $consent['consent_text_hash_reference'],
                'purpose' => 'Berbagi data dan dokumen lamaran dengan pemilik lowongan untuk keperluan rekrutmen.',
                'consented_at' => $now,
                'created_at' => $now,
            ])->save();

            foreach ($documents as $document) {
                $application->documents()->forceCreate([
                    'candidate_document_id' => $document->getKey(),
                    'shared_at' => $now,
                    'snapshot_name' => $document->display_name,
                    'snapshot_storage_reference' => $document->storage_reference,
                    'snapshot_checksum' => $document->checksum,
                ]);
            }

            foreach ($answers as $answer) {
                $application->screeningAnswers()->forceCreate($answer + ['answered_at' => $now]);
            }

            $application->statusHistories()->forceCreate([
                'from_status' => null,
                'to_status' => ApplicationStatus::Applied->value,
                'event_type' => ApplicationEventType::ApplicationCreated->value,
                'actor_user_id' => $actor->getKey(),
                'candidate_visibility' => 'VISIBLE',
                'occurred_at' => $now,
            ]);

            $this->audit->record('application_created', $actor, 'application', (int) $application->getKey(), [
                'vacancy_id' => (int) $vacancy->getKey(),
            ]);

            $this->notifier->submitted(
                $application,
                $actor,
                $company !== null ? (int) $company->getKey() : null,
                $company === null ? (int) $vacancy->created_by : null,
            );

            return $application->refresh();
        });
    }

    private function lockEligibleVacancy(int $vacancyId): Vacancy
    {
        /** @var Vacancy|null $vacancy */
        $vacancy = Vacancy::query()->whereKey($vacancyId)->lockForUpdate()->first();

        // A vacancy that does not exist, has an unknown ownership type, is not
        // PUBLISHED, or is outside its active window are all the same "not
        // currently applicable" fact to the caller; which one is never leaked
        // through a different error shape. CAMPUS is accepted the same as
        // COMPANY — one application lifecycle serves both tracks.
        if ($vacancy === null || ! in_array($vacancy->ownership_type, ['COMPANY', 'CAMPUS'], true)) {
            throw new VacancyNotOpenForApplication();
        }

        $now = now();
        if ($vacancy->current_status !== VacancyStatus::Published
            || $vacancy->open_at === null || $now < $vacancy->open_at
            || $vacancy->close_at === null || $now >= $vacancy->close_at) {
            throw new VacancyNotOpenForApplication();
        }

        if ($vacancy->application_method !== VacancyApplicationMethod::InPortal) {
            throw new ApplicationNotInPortalVacancy();
        }

        return $vacancy;
    }

    /** AD-1, approved and CLOSED: independently rechecked inside this transaction, never inferred from public visibility. */
    private function lockVerifiedCompany(Vacancy $vacancy): Company
    {
        /** @var Company|null $company */
        $company = Company::query()->whereKey($vacancy->company_id)->lockForUpdate()->first();

        if ($company === null || $company->verification_status !== CompanyStatus::Verified) {
            throw new VacancyCompanyNotVerified();
        }

        return $company;
    }

    private function assertAudienceEligible(Vacancy $vacancy, CandidateProfile $profile): void
    {
        $audience = $vacancy->target_audience?->value;

        // INTERNAL (AD-3) and any unrecognized value are not supported in this
        // milestone. The narrowest existing code for "this candidate cannot
        // apply to this audience" is reused rather than inventing a new one.
        if (! in_array($audience, self::SUPPORTED_AUDIENCES, true)) {
            throw new CandidateNotEligible();
        }

        ApplicationEligibility::assert($profile, $audience);
    }

    private function assertNoExistingLifecycle(CandidateProfile $profile, Vacancy $vacancy): void
    {
        $existing = Application::query()
            ->where('candidate_profile_id', $profile->getKey())
            ->where('vacancy_id', $vacancy->getKey())
            ->first();

        if ($existing !== null) {
            throw new ApplicationAlreadyExists((int) $existing->getKey());
        }
    }

    /** @param mixed $consent @return array{consent_version: string, consent_text_hash_reference: string} */
    private function validateConsent(mixed $consent): array
    {
        if (! is_array($consent) || ($consent['accepted'] ?? null) !== true) {
            throw new ApplicationConsentRequired();
        }

        $version = trim((string) ($consent['consent_version'] ?? ''));
        if (! in_array($version, ApplicationConsentVersion::known(), true)) {
            throw new ConsentVersionUnknown();
        }

        // GAP-012 — the client no longer defines the authoritative consent
        // hash. Whatever `consent_text_hash_reference` the request carried
        // (the transport shape still requires the field) is discarded: the
        // persisted reference is derived server-side from the known version,
        // so an arbitrary client value can never become the trusted record.
        return [
            'consent_version' => $version,
            'consent_text_hash_reference' => ApplicationConsentVersion::serverDerivedHash($version),
        ];
    }

    /** @param list<mixed> $documentIds @return list<CandidateDocument> */
    private function validateDocuments(array $documentIds, CandidateProfile $profile): array
    {
        $ids = array_values(array_unique(array_map('intval', $documentIds)));
        $documents = [];

        foreach ($ids as $id) {
            /** @var CandidateDocument|null $document */
            $document = CandidateDocument::query()->whereKey($id)->first();

            if ($document === null || (int) $document->candidate_profile_id !== (int) $profile->getKey()) {
                throw new CandidateDocumentNotOwnedException();
            }
            if ($document->archived_at !== null) {
                throw new ApplicationDocumentArchived();
            }

            $documents[] = $document;
        }

        // APPLICATION_DOCUMENT_REQUIRED is reserved: no authoritative source
        // defines a required-document-type field on vacancies today, so no
        // required-type check is invented here.

        return $documents;
    }

    /** @param list<mixed> $rawAnswers @return list<array<string, mixed>> */
    private function validateScreeningAnswers(Vacancy $vacancy, array $rawAnswers): array
    {
        $questions = $vacancy->screeningQuestions()->where('active', true)
            ->orderBy('sort_order')->orderBy('id')->get()->keyBy(fn (VacancyScreeningQuestion $q) => (int) $q->getKey());

        $bySubmittedId = [];
        foreach ($rawAnswers as $entry) {
            if (! is_array($entry) || ! isset($entry['screening_question_id'])) {
                throw new ApplicationScreeningInvalid();
            }
            $qid = (int) $entry['screening_question_id'];
            // A duplicate answer for the same question in one submission is
            // itself an invalid payload, not a silent overwrite.
            if (isset($bySubmittedId[$qid])) {
                throw new ApplicationScreeningInvalid();
            }
            $bySubmittedId[$qid] = $entry;
        }

        // Every submitted question id must belong to this vacancy's active
        // questions (INV-019) — a foreign or inactive id is invalid, never
        // silently ignored.
        foreach (array_keys($bySubmittedId) as $qid) {
            if (! $questions->has($qid)) {
                throw new ApplicationScreeningInvalid();
            }
        }

        $rows = [];
        foreach ($questions as $question) {
            $qid = (int) $question->getKey();
            $entry = $bySubmittedId[$qid] ?? null;

            if ($entry === null) {
                if ($question->required) {
                    throw new ApplicationScreeningIncomplete();
                }

                continue;
            }

            $rows[] = $this->buildAnswerRow($question, $entry['answer_value'] ?? null);
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function buildAnswerRow(VacancyScreeningQuestion $question, mixed $value): array
    {
        $base = ['screening_question_id' => (int) $question->getKey()];

        return match ($question->question_type) {
            ScreeningQuestionType::ShortText,
            ScreeningQuestionType::LongText => $this->textAnswer($base, $value),
            ScreeningQuestionType::YesNo => $this->booleanAnswer($base, $value),
            ScreeningQuestionType::Number => $this->numberAnswer($base, $value),
            ScreeningQuestionType::SingleChoice => $this->choiceAnswer($base, $value, $question),
        };
    }

    /** @param array<string, mixed> $base @return array<string, mixed> */
    private function textAnswer(array $base, mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            throw new ApplicationScreeningInvalid();
        }

        return $base + ['answer_text' => $value];
    }

    /** @param array<string, mixed> $base @return array<string, mixed> */
    private function booleanAnswer(array $base, mixed $value): array
    {
        if (! is_bool($value)) {
            throw new ApplicationScreeningInvalid();
        }

        return $base + ['answer_boolean' => $value];
    }

    /** @param array<string, mixed> $base @return array<string, mixed> */
    private function numberAnswer(array $base, mixed $value): array
    {
        if (! is_int($value) && ! is_float($value)) {
            throw new ApplicationScreeningInvalid();
        }

        return $base + ['answer_number' => $value];
    }

    /** @param array<string, mixed> $base @return array<string, mixed> */
    private function choiceAnswer(array $base, mixed $value, VacancyScreeningQuestion $question): array
    {
        $options = $question->options_definition ?? [];
        if (! is_string($value) || ! in_array($value, $options, true)) {
            throw new ApplicationScreeningInvalid();
        }

        return $base + ['answer_option' => $value];
    }

    private function insertApplication(CandidateProfile $profile, Vacancy $vacancy, \DateTimeInterface $now): Application
    {
        $application = new Application();
        $application->forceFill([
            'application_code' => ApplicationIdentifier::code(),
            'candidate_profile_id' => $profile->getKey(),
            'vacancy_id' => $vacancy->getKey(),
            'current_status' => ApplicationStatus::Applied,
            'first_applied_at' => $now,
            'reopen_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            $application->save();
        } catch (UniqueConstraintViolationException) {
            // The database is the final race authority (uq_applications_candidate_vacancy):
            // a concurrent winner committed first. Map to the identical contract
            // error a pre-check would have produced, never a raw SQLSTATE.
            $existing = Application::query()
                ->where('candidate_profile_id', $profile->getKey())
                ->where('vacancy_id', $vacancy->getKey())
                ->first();

            throw new ApplicationAlreadyExists((int) ($existing?->getKey() ?? 0));
        }

        return $application;
    }
}
