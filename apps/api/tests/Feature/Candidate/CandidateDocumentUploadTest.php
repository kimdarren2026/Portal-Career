<?php

declare(strict_types=1);

namespace Tests\Feature\Candidate;

use App\Domains\Candidate\Support\CandidateDocumentUploadLimiter;
use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Feature\Identity\IdentityTestCase;

final class CandidateDocumentUploadTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
    }

    public function test_candidate_uploads_an_owned_pdf_to_private_storage_with_safe_metadata_and_audit(): void
    {
        [$candidate, $profileId] = $this->candidate();

        $response = $this->upload($candidate, [
            'file' => $this->pdf('resume.pdf'),
            'document_type' => 'Resume khusus',
            'display_name' => 'Resume Kandidat.pdf',
        ])->assertCreated()
            ->assertHeader('Location', route('candidate.documents.index'))
            ->assertJsonPath('data.document_type', 'Resume khusus')
            ->assertJsonPath('data.display_name', 'Resume Kandidat.pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonMissingPath('data.storage_reference');

        $documentId = (int) $response->json('data.id');
        $document = DB::table('candidate_documents')->where('id', $documentId)->first();

        self::assertNotNull($document);
        self::assertSame($profileId, (int) $document->candidate_profile_id);
        self::assertSame('application/pdf', $document->mime_type);
        self::assertSame($this->pdfBytes(), (int) $document->size);
        self::assertNotEmpty($document->storage_reference);
        self::assertNotNull($document->uploaded_at);
        self::assertNull($document->checksum);
        Storage::disk('local')->assertExists($document->storage_reference);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_uploaded', 'object_id' => $documentId]);

        $audit = DB::table('audit_logs')->where('action', 'document_uploaded')->where('object_id', $documentId)->value('change_summary');
        self::assertEquals([
            'document_type' => 'Resume khusus',
            'size' => $this->pdfBytes(),
            'mime_type' => 'application/pdf',
        ], json_decode((string) $audit, true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_upload_requires_authentication_and_verified_email(): void
    {
        $this->post('/candidate/documents', [
            'file' => $this->pdf(),
            'document_type' => 'CV',
        ], ['Accept' => 'application/json'])->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');

        [$candidate] = $this->candidate(UserStatus::PendingEmailVerification);
        $this->upload($candidate, ['file' => $this->pdf(), 'document_type' => 'CV'])
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_EMAIL_NOT_VERIFIED');
    }

    public function test_server_inspects_pdf_content_and_ignores_client_mime_and_extension(): void
    {
        [$candidate] = $this->candidate();

        $this->upload($candidate, [
            'file' => File::fake()->createWithContent('misleading.txt', $this->pdfContent())->mimeType('text/plain'),
            'document_type' => 'Anything open text',
        ])->assertCreated()->assertJsonPath('data.mime_type', 'application/pdf');

        $this->upload($candidate, [
            'file' => File::fake()->createWithContent('claimed.pdf', 'not a PDF')->mimeType('application/pdf'),
            'document_type' => 'CV',
        ])->assertStatus(415)->assertJsonPath('error.code', 'UNSUPPORTED_MEDIA_TYPE');

        $this->upload($candidate, [
            'file' => File::fake()->createWithContent('leading-space.pdf', ' '.$this->pdfContent())->mimeType('application/pdf'),
            'document_type' => 'CV',
        ])->assertStatus(422)->assertJsonPath('error.code', 'DOCUMENT_TYPE_NOT_ALLOWED');
    }

    public function test_exact_application_size_limit_is_enforced_with_a_413_envelope(): void
    {
        [$candidate] = $this->candidate();

        $exact = File::fake()->createWithContent('exact.pdf', '%PDF-'.str_repeat('A', 10_485_755));
        self::assertSame(10_485_760, $exact->getSize());
        $this->upload($candidate, ['file' => $exact, 'document_type' => 'Arbitrary'])->assertCreated();

        $tooLarge = File::fake()->createWithContent('too-large.pdf', '%PDF-'.str_repeat('A', 10_485_756));
        self::assertSame(10_485_761, $tooLarge->getSize());
        $this->upload($candidate, ['file' => $tooLarge, 'document_type' => 'Arbitrary'])
            ->assertStatus(413)->assertJsonPath('error.code', 'PAYLOAD_TOO_LARGE');
    }

    public function test_open_document_type_and_sanitized_duplicate_display_names_are_allowed(): void
    {
        [$candidate] = $this->candidate();
        $type = str_repeat('x', 64);
        $unsafeName = "../\\resume\x00\r\nname\u{202E}fdp.pdf";

        $first = $this->upload($candidate, [
            'file' => $this->pdf(),
            'document_type' => $type,
            'display_name' => $unsafeName,
        ])->assertCreated();
        $firstName = (string) $first->json('data.display_name');
        self::assertNotSame('', $firstName);
        self::assertStringNotContainsString('/', $firstName);
        self::assertStringNotContainsString('\\', $firstName);
        self::assertStringNotContainsString("\n", $firstName);
        self::assertStringNotContainsString("\r", $firstName);
        self::assertStringNotContainsString("\u{202E}", $firstName);
        self::assertLessThanOrEqual(255, mb_strlen($firstName));

        $this->upload($candidate, [
            'file' => $this->pdf(),
            'document_type' => 'Another custom type',
            'display_name' => $firstName,
        ])->assertCreated();
        self::assertSame(2, DB::table('candidate_documents')->where('display_name', $firstName)->count());

        $this->upload($candidate, [
            'file' => $this->pdf(),
            'document_type' => str_repeat('x', 65),
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_candidate_cannot_choose_another_profile_or_server_storage_metadata(): void
    {
        [$candidate] = $this->candidate();
        [, $otherProfile] = $this->candidate();

        $this->upload($candidate, [
            'file' => $this->pdf(),
            'document_type' => 'CV',
            'candidate_profile_id' => $otherProfile,
            'storage_reference' => 'public/attacker.pdf',
            'mime_type' => 'text/plain',
            'size' => 1,
            'checksum' => 'client-asserted',
            'uploaded_at' => now()->toIso8601String(),
            'archived_at' => now()->toIso8601String(),
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        self::assertSame(0, DB::table('candidate_documents')->where('candidate_profile_id', $otherProfile)->count());
    }

    public function test_invalid_attempts_consume_the_exact_rolling_per_candidate_limit_and_expire(): void
    {
        [$candidate] = $this->candidate();

        for ($attempt = 0; $attempt < CandidateDocumentUploadLimiter::LIMIT; $attempt++) {
            $this->actingAs($candidate)->postJson('/candidate/documents', ['document_type' => 'CV'])
                ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        }

        $this->actingAs($candidate)->postJson('/candidate/documents', ['document_type' => 'CV'])
            ->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED')->assertHeader('Retry-After');

        $this->travel(CandidateDocumentUploadLimiter::WINDOW_SECONDS + 1)->seconds();
        $this->upload($candidate, ['file' => $this->pdf(), 'document_type' => 'CV'])->assertCreated();
    }

    public function test_storage_failure_creates_no_metadata_and_database_failure_cleans_up_the_object(): void
    {
        [$candidate, $profileId] = $this->candidate();
        $originalFilesystem = $this->app->make(FilesystemFactory::class);
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturnFalse();
        $filesystem = Mockery::mock(FilesystemFactory::class);
        $filesystem->shouldReceive('disk')->once()->with('local')->andReturn($disk);
        $this->app->instance(FilesystemFactory::class, $filesystem);

        $storageFailure = $this->upload($candidate, ['file' => $this->pdf(), 'document_type' => 'CV']);
        $storageFailure->assertStatus(500)->assertJsonPath('error.code', 'SERVER_ERROR');
        self::assertSame(0, DB::table('candidate_documents')->where('candidate_profile_id', $profileId)->count());

        $this->app->instance(FilesystemFactory::class, $originalFilesystem);
        Storage::fake('local');
        Event::listen('eloquent.creating: '.CandidateDocument::class, function (): void {
            throw new RuntimeException('Database transaction failure');
        });

        $databaseFailure = $this->upload($candidate, ['file' => $this->pdf(), 'document_type' => 'CV']);
        $databaseFailure->assertStatus(500)->assertJsonPath('error.code', 'SERVER_ERROR');
        self::assertSame(0, DB::table('candidate_documents')->where('candidate_profile_id', $profileId)->count());
        self::assertSame([], Storage::disk('local')->allFiles('candidate-documents/'.$profileId));
    }

    public function test_verification_post_and_versioned_candidate_document_route_remain_inactive(): void
    {
        [$candidate] = $this->candidate();

        $this->actingAs($candidate)->postJson('/candidate/verifications', ['verification_type' => 'ALUMNI'])->assertMethodNotAllowed();
        $this->actingAs($candidate)->postJson('/api/v1/candidate/documents', ['document_type' => 'CV'])->assertNotFound();
    }

    /** @return array{User, int} */
    private function candidate(UserStatus $status = UserStatus::Active): array
    {
        $candidate = $this->makeUser('candidate-upload-'.uniqid('', true).'@example.test', $status);
        $this->assignRole($candidate, RoleCode::CandidateExternal);
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidate->id,
            'current_candidate_type' => 'EXTERNAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(CandidateDocumentUploadLimiter::class)->clear($candidate);

        return [$candidate, $profileId];
    }

    /** @param array<string, mixed> $payload */
    private function upload(User $candidate, array $payload)
    {
        return $this->actingAs($candidate)->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/candidate/documents', $payload);
    }

    private function pdf(): File
    {
        return File::fake()->createWithContent('document.pdf', $this->pdfContent());
    }

    private function pdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n";
    }

    private function pdfBytes(): int
    {
        return strlen($this->pdfContent());
    }
}
