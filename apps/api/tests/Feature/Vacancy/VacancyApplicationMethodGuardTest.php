<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * INV-024 (frozen invariant, not a new decision): an `applications` row may
 * never exist for an EXTERNAL_ATS vacancy, so IN_PORTAL → EXTERNAL_ATS is
 * refused while applications exist → 409 VACANCY_HAS_APPLICATIONS.
 *
 * Applications are inserted as fixture rows only. No Application capability is
 * implemented, routed, or read by this phase.
 */
final class VacancyApplicationMethodGuardTest extends VacancyTestCase
{
    public function test_switch_to_external_ats_is_allowed_when_no_application_exists(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('inv24-free@example.test');
        $id = $this->createVacancy($recruiter, $company);

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", [
            'application_method' => 'EXTERNAL_ATS',
            'external_ats_url' => 'https://ats.example.test/jobs/1',
        ])->assertOk()->assertJsonPath('data.application_method', 'EXTERNAL_ATS');

        self::assertSame('EXTERNAL_ATS', DB::table('vacancies')->where('id', $id)->value('application_method'));
        self::assertSame(2, $this->latestVersion($id));
    }

    public function test_switch_to_external_ats_is_refused_while_an_application_exists_and_writes_nothing(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('inv24-blocked@example.test');
        $skill = $this->skillId();
        $id = $this->createVacancy($recruiter, $company, [
            'requirements' => [['requirement_type' => 'SKILL', 'skill_id' => $skill, 'required' => true, 'sort_order' => 0]],
        ]);
        $this->applicationFor($id, 'inv24-candidate@example.test');

        $requirementsBefore = $this->requirementRows($id);
        $auditBefore = DB::table('audit_logs')->where('action', 'vacancy_updated')->where('object_id', $id)->count();

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", [
            'application_method' => 'EXTERNAL_ATS',
            'external_ats_url' => 'https://ats.example.test/jobs/2',
            'requirements' => [],
        ])->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_HAS_APPLICATIONS');

        $row = DB::table('vacancies')->where('id', $id)->first();
        self::assertSame('IN_PORTAL', $row->application_method, 'application_method must be unchanged.');
        self::assertNull($row->external_ats_url, 'external_ats_url must be unchanged.');
        self::assertSame($requirementsBefore, $this->requirementRows($id), 'requirements must be unchanged.');
        self::assertSame(1, DB::table('vacancy_versions')->where('vacancy_id', $id)->count(), 'no version may be appended.');
        self::assertSame(
            $auditBefore,
            DB::table('audit_logs')->where('action', 'vacancy_updated')->where('object_id', $id)->count(),
            'no vacancy_updated audit entry may be written.',
        );
    }

    public function test_external_ats_back_to_in_portal_is_not_blocked_by_inv_024(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('inv24-reverse@example.test');
        $id = $this->createVacancy($recruiter, $company, [
            'application_method' => 'EXTERNAL_ATS',
            'external_ats_url' => 'https://ats.example.test/jobs/3',
        ]);

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", ['application_method' => 'IN_PORTAL'])
            ->assertOk()->assertJsonPath('data.application_method', 'IN_PORTAL');

        self::assertSame('IN_PORTAL', DB::table('vacancies')->where('id', $id)->value('application_method'));
    }

    public function test_an_unrelated_edit_is_unaffected_by_an_existing_application(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('inv24-unrelated@example.test');
        $id = $this->createVacancy($recruiter, $company);
        $this->applicationFor($id, 'inv24-other-candidate@example.test');

        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}", ['title' => 'Still Editable'])->assertOk();

        self::assertSame('Still Editable', DB::table('vacancies')->where('id', $id)->value('title'));
        self::assertSame(2, $this->latestVersion($id));
    }

    /** Fixture row only — the Application surface is not implemented in this phase. */
    private function applicationFor(int $vacancyId, string $email): void
    {
        $candidate = $this->makeUser($email);
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidate->id,
            'current_candidate_type' => 'EXTERNAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->sequence++;
        DB::table('applications')->insert([
            'application_code' => 'APP-'.$this->sequence.'-'.$vacancyId,
            'candidate_profile_id' => $profileId,
            'vacancy_id' => $vacancyId,
            'current_status' => 'APPLIED',
            'first_applied_at' => now(),
            'reopen_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
