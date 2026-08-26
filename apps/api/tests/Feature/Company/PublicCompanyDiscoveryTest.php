<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Identity\IdentityTestCase;

/**
 * PD-2 (approved 26 August 2026) — GET /api/v1/public/companies/{slug}.
 * Single-company public summary only; the slug carries no authorization
 * meaning, and visibility is governed by the company's own verification
 * status, exactly as the embedded vacancy company summary already is.
 */
final class PublicCompanyDiscoveryTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_verified_company_is_publicly_resolvable_by_slug(): void
    {
        [, $company] = $this->company('resolvable@example.test', CompanyStatus::Verified);

        $this->getJson("/api/v1/public/companies/{$company['slug']}")
            ->assertOk()
            ->assertJsonPath('data.name', $company['name']);
    }

    public function test_non_verified_company_and_nonexistent_slug_are_both_a_plain_404(): void
    {
        [, $draft] = $this->company('draft@example.test', CompanyStatus::Draft);

        $response = $this->getJson("/api/v1/public/companies/{$draft['slug']}");
        $response->assertNotFound();

        $missing = $this->getJson('/api/v1/public/companies/does-not-exist-xyz789');
        $missing->assertNotFound();

        // Indistinguishable — a 403 or a differently shaped body would leak state.
        self::assertSame($response->json('error.code'), $missing->json('error.code'));
        self::assertArrayNotHasKey('details', $response->json('error'));
    }

    public function test_suspended_company_is_hidden_and_restored_company_is_visible_again(): void
    {
        [, $company] = $this->company('suspend-cycle@example.test', CompanyStatus::Verified);

        DB::table('companies')->where('id', $company['id'])->update(['verification_status' => 'SUSPENDED']);
        $this->getJson("/api/v1/public/companies/{$company['slug']}")->assertNotFound();

        DB::table('companies')->where('id', $company['id'])->update(['verification_status' => 'VERIFIED']);
        $this->getJson("/api/v1/public/companies/{$company['slug']}")->assertOk();
    }

    public function test_payload_contains_only_safe_fields(): void
    {
        [, $company] = $this->company('payload@example.test', CompanyStatus::Verified);
        DB::table('company_documents')->insert([
            'company_id' => $company['id'], 'document_type' => 'NIB', 'document_number' => 'SECRET-DOC-123',
            'storage_reference' => 'private/legal/secret.pdf', 'status' => 'ACTIVE',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/public/companies/{$company['slug']}")->assertOk();
        $data = $response->json('data');

        self::assertSame(['name', 'logo_url', 'industry_id', 'city_geographic_area_id', 'mitra_kampus_active'], array_keys($data));
        self::assertArrayNotHasKey('verification_status', $data, 'No unsourced verification field.');
        self::assertArrayNotHasKey('legal_identifier', $data);
        self::assertArrayNotHasKey('official_email', $data);
        self::assertArrayNotHasKey('official_phone', $data);
        self::assertArrayNotHasKey('created_by', $data);
        self::assertArrayNotHasKey('members', $data);
        self::assertArrayNotHasKey('documents', $data);
        self::assertNull($data['logo_url']);

        $body = $response->getContent();
        foreach (['SECRET-DOC-123', 'private/legal/secret.pdf', 'legal_identifier'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $body);
        }
    }

    public function test_slug_is_assigned_once_unique_and_immutable_after_name_edit(): void
    {
        [$admin, $company] = $this->company('slug-stable@example.test', CompanyStatus::Draft);
        $originalSlug = $company['slug'];
        self::assertNotNull($originalSlug);
        self::assertMatchesRegularExpression('/^[a-z0-9-]+$/', $originalSlug);

        $this->actingAs($admin)->patchJson("/companies/{$company['id']}", ['name' => 'Renamed Company Entirely'])
            ->assertOk();

        self::assertSame($originalSlug, DB::table('companies')->where('id', $company['id'])->value('slug'), 'PD-2: a name edit must never regenerate the slug.');
    }

    public function test_two_companies_never_receive_the_same_slug(): void
    {
        [, $a] = $this->company('dup-a@example.test', CompanyStatus::Verified, 'Duplicate Name Co');
        [, $b] = $this->company('dup-b@example.test', CompanyStatus::Verified, 'Duplicate Name Co');

        self::assertNotSame($a['slug'], $b['slug']);
    }

    public function test_no_public_company_directory_route_exists(): void
    {
        $uris = collect(Route::getRoutes())->map(static fn ($route): string => $route->uri());

        self::assertFalse($uris->contains('api/v1/public/companies'), 'PD-2 must not create a Public Company Directory.');
        self::assertTrue($uris->contains('api/v1/public/companies/{slug}'));
    }

    public function test_no_authentication_required(): void
    {
        [, $company] = $this->company('anon@example.test', CompanyStatus::Verified);

        $this->getJson("/api/v1/public/companies/{$company['slug']}")->assertOk();
    }

    /** @return array{0: \App\Domains\Identity\Models\User, 1: array<string, mixed>} */
    private function company(string $email, CompanyStatus $status, string $name = 'Public Discovery Co'): array
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::CompanyRecruiter);

        $response = $this->actingAs($user)->postJson('/companies', [
            'name' => $name,
        ])->assertCreated();

        $id = (int) $response->json('data.id');
        if ($status !== CompanyStatus::Draft) {
            DB::table('companies')->where('id', $id)->update(['verification_status' => $status->value]);
        }

        $row = (array) DB::table('companies')->where('id', $id)->first();

        return [$user, $row];
    }
}
