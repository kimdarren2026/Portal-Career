<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Vacancy\Actions\UpdateVacancy;
use App\Domains\Vacancy\Exceptions\VacancyStaleVersion;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/** FR-VAC-007: same row, append-only versions, optimistic concurrency. */
final class VacancyEditingAndVersionsTest extends VacancyTestCase
{
    public function test_creation_writes_exactly_version_one_representing_the_created_state(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('v1@example.test');
        $id = $this->createVacancy($recruiter, $company, [
            'title' => 'Data Engineer',
            'requirements' => [['requirement_type' => 'SKILL', 'skill_id' => $this->skill(), 'sort_order' => 0]],
            'screening_questions' => [['question_text' => 'Years of SQL?', 'question_type' => 'NUMBER', 'sort_order' => 0]],
        ]);

        $versions = DB::table('vacancy_versions')->where('vacancy_id', $id)->get();
        $this->assertCount(1, $versions);
        $this->assertSame(1, (int) $versions[0]->version_number);

        $snapshot = json_decode((string) $versions[0]->snapshot, true);
        $this->assertSame('Data Engineer', $snapshot['vacancy']['title']);
        $this->assertSame('DRAFT', $snapshot['vacancy']['current_status']);
        $this->assertCount(1, $snapshot['requirements']);
        $this->assertCount(1, $snapshot['screening_questions']);
        // Moderation notes are not part of a recruiter-authored revision.
        $this->assertStringNotContainsString('internal_note', (string) $versions[0]->snapshot);
    }

    public function test_draft_edit_revises_the_same_row_and_appends_exactly_one_version(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('edit@example.test');
        $id = $this->createVacancy($recruiter, $company);

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", ['title' => 'Renamed Role'])
            ->assertOk()->assertJsonPath('data.title', 'Renamed Role')->assertJsonPath('data.current_version', 2);

        $this->assertSame(1, DB::table('vacancies')->count(), 'Editing must never create a replacement vacancy.');
        $this->assertSame(2, DB::table('vacancy_versions')->where('vacancy_id', $id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'vacancy_updated', 'object_id' => $id]);
    }

    public function test_revision_required_is_editable_and_also_appends_one_version(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('revision@example.test');
        $id = $this->createVacancy($recruiter, $company);
        DB::table('vacancies')->where('id', $id)->update(['current_status' => 'REVISION_REQUIRED']);

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", ['title' => 'Corrected Title'])->assertOk();

        $this->assertSame(1, DB::table('vacancies')->count());
        $this->assertSame(2, DB::table('vacancy_versions')->where('vacancy_id', $id)->count());
        $this->assertSame('REVISION_REQUIRED', DB::table('vacancies')->where('id', $id)->value('current_status'));
    }

    public function test_non_editable_states_are_refused(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('locked@example.test');
        $id = $this->createVacancy($recruiter, $company);

        foreach (['PENDING_REVIEW', 'APPROVED', 'SCHEDULED', 'PUBLISHED', 'REJECTED', 'CLOSED', 'EXPIRED', 'SUSPENDED'] as $status) {
            DB::table('vacancies')->where('id', $id)->update(['current_status' => $status]);
            $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", ['title' => 'Nope'])
                ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_NOT_EDITABLE');
        }

        $this->assertSame(1, DB::table('vacancy_versions')->where('vacancy_id', $id)->count());
        $this->assertNotSame('Nope', DB::table('vacancies')->where('id', $id)->value('title'));
    }

    public function test_stale_if_match_is_refused_and_mutates_nothing(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('stale@example.test');
        $id = $this->createVacancy($recruiter, $company);
        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", ['title' => 'First Edit'])->assertOk();

        $this->actingAs($recruiter)
            ->withHeaders(['If-Match' => '1'])
            ->patchJson("/vacancies/{$id}", ['title' => 'Stale Edit'])
            ->assertStatus(409)->assertJsonPath('error.code', 'STALE_VERSION');

        $this->assertSame('First Edit', DB::table('vacancies')->where('id', $id)->value('title'));
        $this->assertSame(2, DB::table('vacancy_versions')->where('vacancy_id', $id)->count());

        // A matching version proceeds.
        $this->actingAs($recruiter)->withHeaders(['If-Match' => '2'])
            ->patchJson("/vacancies/{$id}", ['title' => 'Fresh Edit'])->assertOk();
    }

    public function test_version_numbers_stay_unique_under_repeated_allocation(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('alloc@example.test');
        $id = $this->createVacancy($recruiter, $company);
        $vacancy = Vacancy::findOrFail($id);
        $action = app(UpdateVacancy::class);

        for ($i = 0; $i < 5; $i++) {
            $action->execute($recruiter, $vacancy, ['title' => "Iteration {$i}"]);
        }

        $numbers = DB::table('vacancy_versions')->where('vacancy_id', $id)->pluck('version_number')->all();
        $this->assertSame([1, 2, 3, 4, 5, 6], array_map('intval', $numbers));
        $this->assertSame(count($numbers), count(array_unique($numbers)));

        // The unique index is the backstop even if a caller forgot the lock.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('vacancy_versions')->insert([
            'vacancy_id' => $id, 'version_number' => 6, 'snapshot' => '{}',
            'created_by' => $recruiter->id, 'created_at' => now(),
        ]);
    }

    public function test_versions_endpoint_returns_immutable_history_in_order(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('history@example.test');
        $id = $this->createVacancy($recruiter, $company, ['title' => 'Original']);
        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", ['title' => 'Second'])->assertOk();
        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", ['title' => 'Third'])->assertOk();

        $response = $this->actingAs($recruiter)->getJson("/vacancies/{$id}/versions")->assertOk();
        $this->assertSame([1, 2, 3], $response->json('data.items.*.version_number'));
        $this->assertSame('Original', $response->json('data.items.0.snapshot.vacancy.title'));
        $this->assertSame('Second', $response->json('data.items.1.snapshot.vacancy.title'));
        $this->assertSame('Third', $response->json('data.items.2.snapshot.vacancy.title'));
    }

    public function test_requirements_sync_is_atomic_with_the_parent_create(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('atomic@example.test');

        // A skill reference that does not exist fails validation before any write.
        $this->actingAs($recruiter)->postJson("/companies/{$company->id}/vacancies", $this->payload([
            'requirements' => [['requirement_type' => 'SKILL', 'skill_id' => 999999, 'sort_order' => 0]],
        ]))->assertStatus(422);

        $this->assertDatabaseCount('vacancies', 0);
        $this->assertDatabaseCount('vacancy_requirements', 0);
        $this->assertDatabaseCount('vacancy_versions', 0);
    }

    public function test_career_center_may_read_but_never_edit_a_company_vacancy(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('cc-read@example.test');
        $id = $this->createVacancy($recruiter, $company);

        $reviewer = $this->makeUser('cc-reviewer@example.test', UserStatus::Active);
        $this->assignRole($reviewer, RoleCode::CareerCenterStaff);

        $this->actingAs($reviewer)->getJson("/vacancies/{$id}")->assertOk();
        $this->actingAs($reviewer)->patchJson("/vacancies/{$id}", ['title' => 'Moderator Edit'])
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $auditor = $this->makeUser('auditor@example.test', UserStatus::Active);
        $this->assignRole($auditor, RoleCode::Auditor);
        $this->actingAs($auditor)->getJson("/vacancies/{$id}")->assertOk();
        $this->actingAs($auditor)->patchJson("/vacancies/{$id}", ['title' => 'Auditor Edit'])->assertForbidden();

        $this->assertSame(1, DB::table('vacancy_versions')->where('vacancy_id', $id)->count());
    }

    private function skill(): int
    {
        $this->sequence++;

        return (int) DB::table('skills')->insertGetId([
            'name' => 'SQL '.$this->sequence, 'normalized_name' => 'sql-'.$this->sequence, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
