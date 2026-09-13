<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * "Dokumen Legalitas Perusahaan" frontend surface (recruiter/ProfilPerusahaan
 * section). The section is markup-only against the already-frozen and
 * already-tested `CompanyDocumentController` JSON contract
 * (`tests/Feature/Company/CompanyLegalDocumentTest.php`) — no new route,
 * Action, or policy is introduced. This file covers only what that suite does
 * not: that the recruiter page continues to render correctly once a company
 * has documents, and that personas with no existing coverage against the
 * document endpoints (candidate, guest) stay denied.
 */
final class CompanyLegalDocumentUiIntegrationTest extends VacancyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
    }

    public function test_profil_perusahaan_still_renders_once_the_company_has_a_legal_document(): void
    {
        [$recruiter, $company] = $this->companyWithRecruiter('cldui-page@example.test', CompanyStatus::Draft);

        $this->actingAs($recruiter)->post("/companies/{$company->id}/documents", [
            'file' => File::fake()->createWithContent('nib.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n"),
            'document_type' => 'NIB',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->actingAs($recruiter)->get('/profil-perusahaan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/ProfilPerusahaan')
                ->where('company.id', $company->id)
                ->missing('company.internal_note'),
        );
    }

    public function test_candidate_cannot_read_or_write_company_legal_documents(): void
    {
        [$recruiter, $company] = $this->companyWithRecruiter('cldui-candidate@example.test', CompanyStatus::Draft);
        $docId = (int) $this->actingAs($recruiter)->post("/companies/{$company->id}/documents", [
            'file' => File::fake()->createWithContent('nib.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n"),
            'document_type' => 'NIB',
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $candidate = $this->makeUser('cldui-candidate-actor@example.test', UserStatus::Active);
        $this->assignRole($candidate, RoleCode::CandidateExternal);

        // A candidate is out of CompanyScope entirely (never a member, never a
        // global reader) — the frozen enumeration-safe contract answers 404,
        // never a 403 that would confirm the company/document exists.
        $this->actingAs($candidate)->getJson("/companies/{$company->id}/documents")->assertStatus(404);
        $this->actingAs($candidate)->get("/companies/{$company->id}/documents/{$docId}/download")->assertStatus(404);
        $this->actingAs($candidate)->post("/companies/{$company->id}/documents", [
            'file' => File::fake()->createWithContent('evil.pdf', "%PDF-1.4\n"),
            'document_type' => 'NIB',
        ], ['Accept' => 'application/json'])->assertStatus(404);
        $this->actingAs($candidate)->deleteJson("/companies/{$company->id}/documents/{$docId}")->assertStatus(404);

        // The document itself is untouched by the denied attempts.
        $this->actingAs($recruiter)->getJson("/companies/{$company->id}/documents")
            ->assertOk()->assertJsonPath('data.items.0.id', $docId);
    }

    public function test_guest_cannot_reach_company_legal_documents(): void
    {
        [, $company] = $this->companyWithRecruiter('cldui-guest@example.test', CompanyStatus::Draft);

        $this->getJson("/companies/{$company->id}/documents")->assertStatus(401);
        $this->get('/profil-perusahaan')->assertRedirect('/login');
    }
}
