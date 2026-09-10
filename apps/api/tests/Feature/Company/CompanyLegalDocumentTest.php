<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * PGC-V1 / PD-D — company legal-document runtime + MVP file/type policy.
 */
final class CompanyLegalDocumentTest extends VacancyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
    }

    private function pdf(string $name = 'nib.pdf'): File
    {
        return File::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n");
    }

    private function png(string $name = 'scan.png'): File
    {
        return File::fake()->image($name, 12, 12);
    }

    private function upload(\App\Domains\Identity\Models\User $actor, int $companyId, array $override = [])
    {
        return $this->actingAs($actor)->post("/companies/{$companyId}/documents", array_merge([
            'file' => $this->pdf(),
            'document_type' => 'NIB',
        ], $override), ['Accept' => 'application/json']);
    }

    public function test_recruiter_uploads_lists_and_downloads_a_legal_document_without_exposing_storage(): void
    {
        [$recruiter, $company] = $this->companyWithRecruiter('cld-ok@example.test', CompanyStatus::Draft);

        $docId = (int) $this->upload($recruiter, $company->id, ['file' => $this->png(), 'document_type' => 'AKTA_PENDIRIAN'])
            ->assertCreated()->assertJsonPath('data.document_type', 'AKTA_PENDIRIAN')
            ->assertJsonMissingPath('data.storage_reference')->json('data.id');

        $list = $this->actingAs($recruiter)->getJson("/companies/{$company->id}/documents")->assertOk();
        $list->assertJsonPath('data.items.0.id', $docId)
            ->assertJsonPath('data.items.0.mime_type', 'image/png')
            ->assertJsonMissingPath('data.items.0.storage_reference');

        $this->actingAs($recruiter)->get("/companies/{$company->id}/documents/{$docId}/download")
            ->assertOk()->assertHeader('content-type', 'image/png');

        $this->assertDatabaseHas('audit_logs', ['action' => 'company_document_uploaded', 'object_id' => $docId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_access', 'object_id' => $docId]);
        $stored = (string) DB::table('company_documents')->where('id', $docId)->value('storage_reference');
        self::assertStringStartsWith('company-documents/'.$company->id.'/', $stored);
        self::assertTrue(Storage::disk('local')->exists($stored));
    }

    public function test_mime_and_size_policy_is_enforced_on_real_content(): void
    {
        [$recruiter, $company] = $this->companyWithRecruiter('cld-policy@example.test', CompanyStatus::Draft);

        // A .pdf name but text content — rejected on the sniffed type.
        $fakePdf = File::fake()->createWithContent('evil.pdf', 'just plain text, not a pdf');
        $this->upload($recruiter, $company->id, ['file' => $fakePdf])
            ->assertStatus(415)->assertJsonPath('error.code', 'UNSUPPORTED_MEDIA_TYPE');

        // Oversize.
        $tooLarge = File::fake()->createWithContent('big.pdf', '%PDF-'.str_repeat('A', 10_485_760));
        $this->upload($recruiter, $company->id, ['file' => $tooLarge])
            ->assertStatus(422); // request-rule ceiling (max:10240 KiB) trips first

        // Unknown type code.
        $this->upload($recruiter, $company->id, ['document_type' => 'PASSPORT'])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_career_center_may_download_for_review_but_never_upload_or_delete(): void
    {
        [$recruiter, $company] = $this->companyWithRecruiter('cld-cc@example.test', CompanyStatus::Draft);
        $docId = (int) $this->upload($recruiter, $company->id)->assertCreated()->json('data.id');

        $cc = $this->makeUser('cld-ccuser@example.test', UserStatus::Active);
        $this->assignRole($cc, RoleCode::CareerCenterStaff);

        $this->actingAs($cc)->getJson("/companies/{$company->id}/documents")->assertOk();
        $this->actingAs($cc)->get("/companies/{$company->id}/documents/{$docId}/download")->assertOk();
        $this->upload($cc, $company->id)->assertStatus(403);
        $this->actingAs($cc)->deleteJson("/companies/{$company->id}/documents/{$docId}")->assertStatus(403);
    }

    public function test_cross_company_access_is_a_404(): void
    {
        [$recruiterA, $companyA] = $this->companyWithRecruiter('cld-a@example.test', CompanyStatus::Draft);
        $docId = (int) $this->upload($recruiterA, $companyA->id)->assertCreated()->json('data.id');
        [$recruiterB, $companyB] = $this->companyWithRecruiter('cld-b@example.test', CompanyStatus::Draft);

        $this->actingAs($recruiterB)->getJson("/companies/{$companyB->id}/documents/{$docId}/download")
            ->assertStatus(404);
        // Probing company A directly is also a 404 (out of scope).
        $this->actingAs($recruiterB)->getJson("/companies/{$companyA->id}/documents")->assertStatus(404);
    }

    public function test_draft_delete_is_allowed_but_submitted_evidence_must_be_superseded(): void
    {
        [$recruiter, $company] = $this->companyWithRecruiter('cld-supersede@example.test', CompanyStatus::Draft);
        $draftId = (int) $this->upload($recruiter, $company->id)->assertCreated()->json('data.id');

        // A draft (never submitted) can be deleted outright.
        $this->actingAs($recruiter)->deleteJson("/companies/{$company->id}/documents/{$draftId}")
            ->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertDatabaseMissing('company_documents', ['id' => $draftId]);

        // A document that has formed part of a submitted verification package.
        $evidenceId = (int) $this->upload($recruiter, $company->id, ['document_type' => 'SK_KEMENKUMHAM'])->assertCreated()->json('data.id');
        DB::table('company_documents')->where('id', $evidenceId)->update(['first_submitted_at' => now()]);

        // Now a destructive delete is refused.
        $this->actingAs($recruiter)->deleteJson("/companies/{$company->id}/documents/{$evidenceId}")
            ->assertStatus(409)->assertJsonPath('error.code', 'COMPANY_DOCUMENT_IS_VERIFICATION_EVIDENCE');

        // Supersede with a fresh upload — predecessor retained.
        $successorId = (int) $this->upload($recruiter, $company->id, ['document_type' => 'SK_KEMENKUMHAM'])->assertCreated()->json('data.id');
        $this->actingAs($recruiter)->postJson("/companies/{$company->id}/documents/{$evidenceId}/supersede", [
            'successor_document_id' => $successorId,
        ])->assertOk();

        $pred = DB::table('company_documents')->where('id', $evidenceId)->first();
        self::assertNotNull($pred);
        self::assertNotNull($pred->superseded_at);
        self::assertSame($successorId, (int) $pred->superseded_by_document_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'company_document_superseded', 'object_id' => $evidenceId]);
    }
}
