<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * M-1 — every accepted CLOSE appends exactly one lifecycle-history row that
 * records whoever executed it, on both authorization paths. The row is
 * evidence, not authority: an owner close grants its actor nothing elsewhere.
 */
final class VacancyCloseHistoryTest extends VacancyTestCase
{
    public function test_owner_close_appends_exactly_one_close_row_attributed_to_the_owner(): void
    {
        [$recruiter, $company, $id] = $this->published('close-hist-owner@example.test');

        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/close")
            ->assertOk()->assertJsonPath('data.current_status', 'CLOSED');

        $closeRows = array_values(array_filter(
            $this->reviewRows($id),
            static fn (array $row): bool => $row['action'] === 'CLOSE',
        ));

        self::assertCount(1, $closeRows, 'Exactly one CLOSE history row.');
        self::assertSame('PUBLISHED', $closeRows[0]['from_status']);
        self::assertSame('CLOSED', $closeRows[0]['to_status']);
        self::assertSame($recruiter->id, (int) $closeRows[0]['reviewer_user_id']);
        self::assertNull($closeRows[0]['reason_category'], 'Close carries no mandatory reason.');
        self::assertSame(1, $this->auditCount('vacancy_closed', $id));
    }

    public function test_moderator_close_appends_exactly_one_close_row_attributed_to_the_moderator(): void
    {
        foreach ([RoleCode::CareerCenterStaff, RoleCode::SuperAdmin] as $index => $role) {
            [$recruiter, $company, $id] = $this->published("close-hist-mod-{$index}@example.test");
            $moderator = $this->moderator("close-hist-mod-actor-{$index}@example.test", $role);

            $this->actingAs($moderator)->postJson("/vacancies/{$id}/close")
                ->assertOk()->assertJsonPath('data.current_status', 'CLOSED');

            $closeRows = array_values(array_filter(
                $this->reviewRows($id),
                static fn (array $row): bool => $row['action'] === 'CLOSE',
            ));

            self::assertCount(1, $closeRows);
            self::assertSame('PUBLISHED', $closeRows[0]['from_status']);
            self::assertSame('CLOSED', $closeRows[0]['to_status']);
            self::assertSame($moderator->id, (int) $closeRows[0]['reviewer_user_id']);
            self::assertNotSame($recruiter->id, (int) $closeRows[0]['reviewer_user_id']);
            self::assertSame(1, $this->auditCount('vacancy_closed', $id));
        }
    }

    public function test_writing_a_close_history_row_grants_the_owner_no_moderation_capability(): void
    {
        [$recruiter, $company, $closed] = $this->published('close-hist-authority@example.test');
        $this->actingAs($recruiter)->postJson("/vacancies/{$closed}/close")->assertOk();
        self::assertSame(1, DB::table('vacancy_moderation_reviews')->where('vacancy_id', $closed)
            ->where('action', 'CLOSE')->count());

        // Having authored a CLOSE history row changes nothing about what this
        // actor may do next: moderation stays denied on a fresh vacancy.
        $pending = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => now()->subHour()->toIso8601String(),
            'close_at' => now()->addDays(9)->toIso8601String(),
        ]);

        foreach (['approve', 'reject', 'request-revision', 'suspend', 'restore'] as $action) {
            $this->actingAs($recruiter)->postJson("/vacancies/{$pending}/{$action}", [
                'reason_category' => 'X', 'recruiter_visible_note' => 'Y',
            ])->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }

        self::assertSame('PENDING_REVIEW', DB::table('vacancies')->where('id', $pending)->value('current_status'));
        self::assertSame([], $this->reviewRows($pending));
    }

    public function test_a_repeated_close_cannot_append_a_second_history_row(): void
    {
        [$recruiter, $company, $id] = $this->published('close-hist-repeat@example.test');
        $moderator = $this->moderator('close-hist-repeat-cc@example.test');

        $this->actingAs($recruiter)->withHeaders(['Idempotency-Key' => 'close-key'])
            ->postJson("/vacancies/{$id}/close")->assertOk();

        // Replay of the same key returns the retained response and re-executes
        // nothing.
        $this->actingAs($recruiter)->withHeaders(['Idempotency-Key' => 'close-key'])
            ->postJson("/vacancies/{$id}/close")->assertOk()->assertHeader('Idempotency-Replayed', 'true');

        // A genuinely fresh attempt — no key, and a different key — is refused
        // because CLOSED is not a legal source. Neither path adds a second row.
        $this->flushHeaders();
        $this->actingAs($recruiter)->postJson("/vacancies/{$id}/close")
            ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');
        $this->actingAs($recruiter)->withHeaders(['Idempotency-Key' => 'close-key-2'])
            ->postJson("/vacancies/{$id}/close")
            ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');
        $this->flushHeaders();
        $this->actingAs($moderator)->postJson("/vacancies/{$id}/close")
            ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');

        self::assertSame(1, DB::table('vacancy_moderation_reviews')->where('vacancy_id', $id)
            ->where('action', 'CLOSE')->count());
        self::assertSame(1, $this->auditCount('vacancy_closed', $id));
    }

    /** @return array{User, \App\Domains\Company\Models\Company, int} */
    private function published(string $email): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => now()->subHour()->toIso8601String(),
            'close_at' => now()->addDays(30)->toIso8601String(),
        ]);
        $approver = $this->moderator('close-hist-approver-'.$email);
        $this->actingAs($approver)->postJson("/vacancies/{$id}/approve")
            ->assertOk()->assertJsonPath('data.current_status', 'PUBLISHED');

        return [$recruiter, $company, $id];
    }
}
