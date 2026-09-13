<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domains\Company\Support\CompanyVerificationNotifier;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Recruiter Notification Frontend Slice v7 — the in-app notification centre
 * (API_CONTRACT.md Part IX, reclassified INERTIA_WEB by approved PO / SPEC-DOC
 * decision). Covers OWN scoping, cross-user isolation, the frozen
 * filter/pagination contract, `meta.unread_count`, mark-one / mark-all,
 * enumeration-safe cross-user read, presenter safety (no `email_outbox`
 * leakage) and the page persona gate.
 */
final class NotificationInboxTest extends VacancyTestCase
{
    private function recruiter(string $email): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::CompanyRecruiter);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function notify(User $user, array $overrides = []): int
    {
        return (int) DB::table('notifications')->insertGetId(array_merge([
            'user_id' => $user->getKey(),
            'type' => 'VACANCY_LIFECYCLE',
            'title' => 'VACANCY_LIFECYCLE',
            'body_reference' => 'vacancy.lifecycle.approve',
            'related_object_type' => 'vacancy',
            'related_object_id' => 999,
            'read_at' => null,
            'created_at' => now(),
        ], $overrides));
    }

    public function test_list_is_own_scoped_and_isolates_other_users(): void
    {
        $a = $this->recruiter('notif-a@example.test');
        $b = $this->recruiter('notif-b@example.test');
        $mine = $this->notify($a, ['title' => 'MINE']);
        $this->notify($b, ['title' => 'THEIRS']);

        $this->actingAs($a)->getJson('/notifications')->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $mine)
            ->assertJsonPath('data.items.0.title', 'MINE')
            ->assertJsonMissing(['title' => 'THEIRS'])
            ->assertJsonPath('meta.unread_count', 1);

        // The Inertia page is scoped identically.
        $this->actingAs($a)->get('/notifikasi')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/Notifikasi')
                ->where('unread_count', 1)
                ->has('items', 1)
                ->where('items.0.id', $mine),
        );
    }

    public function test_read_filter_and_pagination_contract(): void
    {
        $a = $this->recruiter('notif-page@example.test');
        for ($i = 0; $i < 25; $i++) {
            $this->notify($a, ['title' => "N{$i}", 'read_at' => $i < 5 ? now() : null]);
        }

        $this->actingAs($a)->getJson('/notifications')->assertOk()
            ->assertJsonPath('data.pagination.total', 25)
            ->assertJsonPath('data.pagination.per_page', 20)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('meta.unread_count', 20);

        $this->actingAs($a)->getJson('/notifications?read=false')->assertOk()
            ->assertJsonPath('data.pagination.total', 20);
        $this->actingAs($a)->getJson('/notifications?read=true')->assertOk()
            ->assertJsonPath('data.pagination.total', 5);

        $this->actingAs($a)->getJson('/notifications?bogus=1')->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_presenter_exposes_only_frozen_safe_fields(): void
    {
        $a = $this->recruiter('notif-safe@example.test');
        $this->notify($a);
        // An unrelated outbox row that must never surface in the inbox.
        DB::table('email_outbox')->insert([
            'recipient' => 'notif-safe@example.test', 'template_reference' => 'vacancy.lifecycle.approve',
            'payload_reference' => json_encode(['secret' => 'DO_NOT_LEAK']), 'status' => 'DEAD_LETTER',
            'attempt_count' => 3, 'next_attempt_at' => null, 'created_at' => now(),
        ]);

        $response = $this->actingAs($a)->getJson('/notifications')->assertOk();
        $response->assertJsonStructure([
            'data' => ['items' => [['id', 'type', 'title', 'body_reference', 'related_object_type', 'related_object_id', 'read_at', 'created_at']]],
            'meta' => ['unread_count'],
        ]);
        $body = $response->getContent() ?: '';
        $this->assertStringNotContainsString('DO_NOT_LEAK', $body);
        $this->assertStringNotContainsString('attempt_count', $body);
        $this->assertStringNotContainsString('DEAD_LETTER', $body);
    }

    public function test_mark_one_read_is_own_only_and_idempotent(): void
    {
        $a = $this->recruiter('notif-mark-a@example.test');
        $b = $this->recruiter('notif-mark-b@example.test');
        $mine = $this->notify($a);
        $theirs = $this->notify($b);

        $this->actingAs($a)->postJson("/notifications/{$mine}/read")->assertNoContent();
        $this->assertNotNull(DB::table('notifications')->where('id', $mine)->value('read_at'));

        // Idempotent — second call still 204, timestamp unchanged.
        $firstReadAt = DB::table('notifications')->where('id', $mine)->value('read_at');
        $this->actingAs($a)->postJson("/notifications/{$mine}/read")->assertNoContent();
        $this->assertSame($firstReadAt, DB::table('notifications')->where('id', $mine)->value('read_at'));

        // Another user's notification is enumeration-safe 404, and stays unread.
        $this->actingAs($a)->postJson("/notifications/{$theirs}/read")->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
        $this->assertNull(DB::table('notifications')->where('id', $theirs)->value('read_at'));
    }

    public function test_mark_all_read_touches_only_the_actor(): void
    {
        $a = $this->recruiter('notif-all-a@example.test');
        $b = $this->recruiter('notif-all-b@example.test');
        $this->notify($a);
        $this->notify($a);
        $bRow = $this->notify($b);

        $this->actingAs($a)->postJson('/notifications/read-all')->assertNoContent();

        $this->assertSame(0, DB::table('notifications')->where('user_id', $a->getKey())->whereNull('read_at')->count());
        $this->assertNull(DB::table('notifications')->where('id', $bRow)->value('read_at'));
    }

    public function test_page_requires_recruiter_persona(): void
    {
        $candidate = $this->makeUser('notif-candidate@example.test', UserStatus::Active);
        $this->assignRole($candidate, RoleCode::CandidateAlumni);

        $this->actingAs($candidate)->get('/notifikasi')->assertStatus(403);
        // The JSON contract route stays open to every authenticated persona.
        $this->actingAs($candidate)->getJson('/notifications')->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_unauthenticated_json_request_is_contract_401(): void
    {
        $this->getJson('/notifications')->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_inbox_reflects_a_genuinely_generated_notifier_row(): void
    {
        [$recruiter, $company] = $this->companyWithRecruiter('notif-real@example.test', \App\Domains\Company\Enums\CompanyStatus::Verified);

        // Use a real runtime event value (CompanyReviewAction::Verify->value);
        // OutboxWriter now rejects a reference with no template renderer.
        app(CompanyVerificationNotifier::class)->queue($company, 'VERIFY');

        $this->actingAs($recruiter)->getJson('/notifications')->assertOk()
            ->assertJsonPath('data.items.0.type', 'COMPANY_VERIFICATION')
            ->assertJsonPath('data.items.0.related_object_type', 'company')
            ->assertJsonPath('meta.unread_count', 1);
    }
}
