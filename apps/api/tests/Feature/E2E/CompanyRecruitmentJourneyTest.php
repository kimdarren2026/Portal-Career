<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Domains\Application\Support\ApplicationConsentVersion;
use App\Domains\Identity\Actions\IssueEmailVerificationToken;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Full company recruitment journey exercised end to end through the real
 * HTTP routes — no DB shortcuts for company documents, verification passage,
 * consent, application-document sharing, or the email outbox. The real
 * transactional-email delivery worker / state machine executes via
 * `outbox:sweep`. (PGC-V1 batch 1B acceptance.)
 */
final class CompanyRecruitmentJourneyTest extends VacancyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
        // The auth-abuse throttles live in the shared isolated test Redis db;
        // clear them so a repeated run of this journey is not rate-limited.
        \Illuminate\Support\Facades\Cache::store('redis')->flush();
        \Illuminate\Support\Facades\RateLimiter::clear('vacancy-report:ip:'.hash('sha256', '127.0.0.1'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_register_verify_onboard_moderate_publish_apply_process_hire(): void
    {
        Carbon::setTestNow('2026-10-01T08:00:00+00:00');

        // 1. Recruiter registration + real verification-token lifecycle.
        $this->postJson('/auth/register/recruiter', [
            'name' => 'Recruiter E2E', 'email' => 'e2e-recruiter@example.test',
            'password' => 'RecruiterPass1', 'password_confirmation' => 'RecruiterPass1', 'accepted_terms' => true,
        ])->assertAccepted();
        /** @var User $recruiter */
        $recruiter = User::query()->where('email', 'e2e-recruiter@example.test')->firstOrFail();
        $this->assertSame(UserStatus::PendingEmailVerification, $recruiter->status);

        $rawToken = app(IssueEmailVerificationToken::class)->execute($recruiter, queueEmail: true);
        $this->postJson('/auth/verify-email', ['token' => $rawToken])->assertOk();
        $this->assertSame(UserStatus::Active, $recruiter->fresh()->status);

        // Identity registration deliberately assigns no company role (FSD open
        // decision 6 / D-1); the COMPANY_RECRUITER grant is a separate step, as
        // every company test establishes.
        $this->assignRole($recruiter, RoleCode::CompanyRecruiter);

        // 2. Company profile + completeness.
        $companyId = (int) $this->actingAs($recruiter->fresh())->postJson('/companies', ['name' => 'E2E Manufaktur'])
            ->assertCreated()->json('data.id');

        $this->sequence++;
        $orgType = DB::table('organization_types')->insertGetId(['code' => 'E2E-OT-'.$this->sequence, 'name' => 'PT', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $industry = DB::table('industries')->insertGetId(['code' => 'E2E-IN-'.$this->sequence, 'name' => 'Manufaktur', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $prov = DB::table('geographic_areas')->insertGetId(['name' => 'Bali', 'area_type' => 'PROVINCE', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $city = DB::table('geographic_areas')->insertGetId(['name' => 'Tabanan', 'area_type' => 'CITY', 'parent_geographic_area_id' => $prov, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($recruiter)->patchJson("/companies/{$companyId}", [
            'organization_type_id' => $orgType, 'industry_id' => $industry,
            'official_email' => 'legal@e2e.example.test', 'address' => 'Jl. Raya Tabanan 1',
            'province_geographic_area_id' => $prov, 'city_geographic_area_id' => $city,
        ])->assertOk();

        // 3. Legal document uploaded through the real runtime endpoint (no DB shortcut).
        $docId = (int) $this->actingAs($recruiter)->post("/companies/{$companyId}/documents", [
            'file' => File::fake()->createWithContent('nib.pdf', "%PDF-1.4\nNIB\nendobj\n"),
            'document_type' => 'NIB',
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        self::assertTrue(Storage::disk('local')->exists(
            (string) DB::table('company_documents')->where('id', $docId)->value('storage_reference')
        ));

        // 4. Submit for verification, Career Center verifies.
        $this->actingAs($recruiter)->postJson("/companies/{$companyId}/submit-verification")->assertOk()
            ->assertJsonPath('data.verification_status', 'PENDING_VERIFICATION');

        $careerCenter = $this->makeUser('e2e-cc@example.test', UserStatus::Active);
        $this->assignRole($careerCenter, RoleCode::CareerCenterManager);
        $this->actingAs($careerCenter)->postJson("/companies/{$companyId}/verify")->assertOk()
            ->assertJsonPath('data.verification_status', 'VERIFIED');

        // 5. Create + submit + moderate + publish a company vacancy.
        $open = Carbon::parse('2026-10-01T00:00:00+00:00');
        $closeAt = Carbon::parse('2026-12-01T00:00:00+00:00');
        $vacancyId = (int) $this->actingAs($recruiter)->postJson("/companies/{$companyId}/vacancies",
            $this->submittablePayload([
                'open_at' => $open->toIso8601String(), 'close_at' => $closeAt->toIso8601String(),
            ]),
        )->assertCreated()->json('data.id');

        $this->actingAs($recruiter)->postJson("/vacancies/{$vacancyId}/submit-review")->assertOk();
        $this->actingAs($careerCenter)->postJson("/vacancies/{$vacancyId}/approve")->assertOk()
            ->assertJsonPath('data.current_status', 'PUBLISHED');
        $slug = (string) DB::table('vacancies')->where('id', $vacancyId)->value('slug');

        // 6. Candidate registers a profile + applies with the ratified consent text
        //    + a shared document.
        $candidate = $this->makeUser('e2e-candidate@example.test', UserStatus::Active);
        $this->assignRole($candidate, RoleCode::CandidateExternal);
        $profileId = (int) DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidate->id, 'current_candidate_type' => 'EXTERNAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $cvRef = 'candidate-documents/'.$profileId.'/cv.pdf';
        Storage::disk('local')->put($cvRef, "%PDF-1.4\nCV E2E\nendobj\n");
        $cvId = (int) DB::table('candidate_documents')->insertGetId([
            'candidate_profile_id' => $profileId, 'document_type' => 'CV', 'display_name' => 'CV E2E.pdf',
            'storage_reference' => $cvRef, 'mime_type' => 'application/pdf', 'size' => 24,
            'uploaded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $applicationId = (int) $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/applications", [
            'consent' => [
                'consent_version' => ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => 'client-supplied-ignored',
                'accepted' => true,
            ],
            'document_ids' => [$cvId],
        ])->assertCreated()->json('data.id');

        // Consent is bound to the ratified canonical text, server-side.
        self::assertSame(
            ApplicationConsentVersion::serverDerivedHash(ApplicationConsentVersion::CURRENT),
            DB::table('consents')->where('application_id', $applicationId)->value('consent_text_hash_reference'),
        );

        // 7. Recruiter opens the shared application document (PD-A).
        $shareId = (int) DB::table('application_documents')->where('application_id', $applicationId)->value('id');
        $this->actingAs($recruiter)->get("/application-documents/{$shareId}/download")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_access', 'object_id' => $shareId]);

        // 8. Process the candidate: UNDER_REVIEW -> SHORTLISTED, interview schedule,
        //    evaluation, offer, accept, outcome.
        foreach (['UNDER_REVIEW', 'SHORTLISTED'] as $to) {
            $this->actingAs($recruiter)->postJson("/applications/{$applicationId}/transition", [
                'to_status' => $to, 'candidate_visibility' => 'VISIBLE',
            ])->assertOk();
        }

        $stageId = (int) $this->actingAs($recruiter)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'Wawancara', 'stage_type' => 'GENERAL', 'sort_order' => 0, 'active' => true,
        ])->assertCreated()->json('data.id');
        $this->actingAs($recruiter)->postJson("/applications/{$applicationId}/schedules", [
            'recruitment_stage_id' => $stageId, 'selection_type' => 'INTERVIEW',
            'starts_at' => now()->addDays(3)->toIso8601String(),
            'timezone' => 'Asia/Makassar', 'method' => 'ONLINE',
            'meeting_url' => 'https://meet.example.test/e2e',
        ])->assertCreated();
        $evalId = (int) $this->actingAs($recruiter)->postJson("/applications/{$applicationId}/evaluations", [
            'recruitment_stage_id' => $stageId, 'recommendation' => 'HIRE',
            'comments' => 'Sangat kompeten.', 'total_score' => 90.0,
        ])->assertCreated()->json('data.id');
        $this->actingAs($recruiter)->postJson("/evaluations/{$evalId}/submit", [])->assertOk();

        $offerId = (int) $this->actingAs($recruiter)->postJson("/applications/{$applicationId}/offers", [
            'note' => 'Selamat bergabung.',
        ])->assertCreated()->json('data.id');
        $this->actingAs($recruiter)->postJson("/offers/{$offerId}/send", [], ['Idempotency-Key' => 'e2e-send'])
            ->assertOk()->assertJsonPath('data.status', 'SENT');
        $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [])
            ->assertOk()->assertJsonPath('data.status', 'ACCEPTED');
        self::assertSame('HIRED', DB::table('applications')->where('id', $applicationId)->value('current_status'));

        $this->actingAs($recruiter)->postJson('/recruitment-outcomes', [
            'source_type' => 'INTERNAL_APPLICATION', 'application_id' => $applicationId,
            'outcome' => 'HIRED', 'reported_by_source' => 'COMPANY',
        ], ['Idempotency-Key' => 'e2e-outcome'])->assertCreated();
        $this->assertDatabaseHas('recruitment_outcomes', [
            'application_id' => $applicationId, 'source_type' => 'INTERNAL_APPLICATION', 'outcome' => 'HIRED',
        ]);

        // 9. The real outbox delivery worker/state machine executed: the
        //    afterCommit dispatch delivered every queued transactional email
        //    (SENT), and a follow-up sweep finds nothing stranded.
        self::assertGreaterThan(0, DB::table('email_outbox')->count(), 'The journey must have queued transactional email.');
        self::assertGreaterThan(0, DB::table('email_outbox')->where('status', 'SENT')->count());

        $this->artisan('outbox:sweep')->assertSuccessful();

        self::assertSame(0, DB::table('email_outbox')->whereIn('status', ['PENDING', 'FAILED_RETRYABLE', 'PROCESSING'])->count());
        self::assertSame(0, DB::table('email_outbox')->where('status', 'DEAD_LETTER')->count());
        self::assertSame(0, DB::table('email_outbox')->whereNotNull('last_error_summary')->count());
        // The raw verification token never reached the outbox.
        self::assertSame(0, DB::table('email_outbox')->where('payload_reference', 'like', '%'.$rawToken.'%')->count());
    }
}
