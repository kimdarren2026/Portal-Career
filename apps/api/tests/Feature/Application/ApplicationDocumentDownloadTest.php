<?php

declare(strict_types=1);

namespace Tests\Feature\Application;

use App\Domains\Application\Support\ApplicationConsentVersion;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * PGC-V1 / PD-A — application-shared document snapshot download. Supersedes
 * RA-3 for the download operation (API_CONTRACT.md Part X items 22 / 62).
 */
final class ApplicationDocumentDownloadTest extends VacancyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{User, \App\Domains\Company\Models\Company, int, int, int} recruiter, company, vacancyId, candidateUserId, applicationDocumentId */
    private function sharedApplicationDocument(string $email): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PUBLISHED', [
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ]);
        DB::table('vacancies')->where('id', $vacancyId)->update(['published_at' => $open]);
        Carbon::setTestNow($close->copy()->subDay());

        $candidate = $this->makeUser('cand-'.$email, UserStatus::Active);
        $this->assignRole($candidate, RoleCode::CandidateExternal);
        $profileId = (int) DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidate->id, 'current_candidate_type' => 'EXTERNAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $storageRef = 'candidate-documents/'.$profileId.'/'.uniqid('', true).'.pdf';
        Storage::disk('local')->put($storageRef, "%PDF-1.4\nshared snapshot\n");
        $docId = (int) DB::table('candidate_documents')->insertGetId([
            'candidate_profile_id' => $profileId, 'document_type' => 'CV', 'display_name' => 'CV Saya.pdf',
            'storage_reference' => $storageRef, 'mime_type' => 'application/pdf', 'size' => 20,
            'uploaded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $applicationId = (int) $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/applications", [
            'consent' => [
                'consent_version' => ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => 'client-value',
                'accepted' => true,
            ],
            'document_ids' => [$docId],
        ])->assertCreated()->json('data.id');

        $shareId = (int) DB::table('application_documents')->where('application_id', $applicationId)->value('id');

        return [$recruiter, $company, $vacancyId, (int) $candidate->id, $shareId];
    }

    public function test_owning_candidate_and_owning_recruiter_can_download_the_snapshot(): void
    {
        [$recruiter, , , $candidateUserId, $shareId] = $this->sharedApplicationDocument('adl-ok@example.test');
        $candidate = User::findOrFail($candidateUserId);

        $this->actingAs($candidate)->get("/application-documents/{$shareId}/download")
            ->assertOk()->assertHeader('content-disposition');
        $this->actingAs($recruiter)->get("/application-documents/{$shareId}/download")->assertOk();

        $this->assertSame(2, DB::table('audit_logs')
            ->where('action', 'document_access')->where('object_id', $shareId)
            ->where('change_summary', 'like', '%streamed%')->count());
    }

    public function test_cross_company_recruiter_and_career_center_and_auditor_get_an_enumeration_safe_404(): void
    {
        [, , , , $shareId] = $this->sharedApplicationDocument('adl-deny@example.test');

        [$otherRecruiter] = $this->verifiedCompanyWithRecruiter('adl-other@example.test');
        $careerCenter = $this->makeUser('adl-cc@example.test', UserStatus::Active);
        $this->assignRole($careerCenter, RoleCode::CareerCenterStaff);
        $auditor = $this->makeUser('adl-aud@example.test', UserStatus::Active);
        $this->assignRole($auditor, RoleCode::Auditor);

        foreach ([$otherRecruiter, $careerCenter, $auditor] as $actor) {
            $this->actingAs($actor)->getJson("/application-documents/{$shareId}/download")
                ->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        }

        // Every denied attempt is audited.
        $this->assertGreaterThanOrEqual(3, DB::table('audit_logs')
            ->where('action', 'document_access')->where('object_id', $shareId)
            ->where('change_summary', 'like', '%denied%')->count());
    }

    public function test_a_revoked_share_is_no_longer_downloadable(): void
    {
        [$recruiter, , , , $shareId] = $this->sharedApplicationDocument('adl-revoked@example.test');

        DB::table('application_documents')->where('id', $shareId)->update(['revoked_at' => now()]);

        $this->actingAs($recruiter)->getJson("/application-documents/{$shareId}/download")
            ->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_a_missing_stored_object_is_a_safe_404_not_a_500(): void
    {
        [$recruiter, , , , $shareId] = $this->sharedApplicationDocument('adl-missing@example.test');

        $ref = (string) DB::table('application_documents')->where('id', $shareId)->value('snapshot_storage_reference');
        Storage::disk('local')->delete($ref);

        $this->actingAs($recruiter)->getJson("/application-documents/{$shareId}/download")
            ->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_access', 'object_id' => $shareId]);
    }

    public function test_unknown_id_is_a_safe_404(): void
    {
        [$recruiter] = $this->verifiedCompanyWithRecruiter('adl-unknown@example.test');
        $this->actingAs($recruiter)->getJson('/application-documents/999999/download')
            ->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_download_requires_authentication(): void
    {
        $this->getJson('/application-documents/1/download')
            ->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
