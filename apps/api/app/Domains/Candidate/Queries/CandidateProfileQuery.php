<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Queries;

use App\Domains\Candidate\Exceptions\CandidateProfileRequiredException;
use App\Domains\Candidate\Models\CandidateCertification;
use App\Domains\Candidate\Models\CandidateEducation;
use App\Domains\Candidate\Models\CandidateLink;
use App\Domains\Candidate\Models\CandidateOrganization;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Candidate\Models\CandidateSkill;
use App\Domains\Candidate\Models\CandidateVerification;
use App\Domains\Candidate\Models\CandidateWorkExperience;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Candidate reads are always rooted at the authenticated owner's profile. */
final class CandidateProfileQuery
{
    public function forUser(User $user): CandidateProfile
    {
        $profile = CandidateProfile::query()->where('user_id', $user->getKey())->first();
        if ($profile === null) {
            throw new CandidateProfileRequiredException('Candidate profile shell is missing.');
        }

        return $profile;
    }

    /** @param list<string> $includes @return array<string, mixed> */
    public function profile(CandidateProfile $profile, array $includes = []): array
    {
        $data = $this->profileAttributes($profile) + [
            'verification_summary' => CandidateVerification::query()
                ->where('candidate_profile_id', $profile->getKey())
                ->orderBy('verification_type')->orderByDesc('updated_at')
                ->get()->map(fn (CandidateVerification $verification): array => $this->verification($verification))->all(),
            'collections' => [
                'educations' => $this->collectionLink('educations', CandidateEducation::query()->where('candidate_profile_id', $profile->getKey())->count()),
                'work_experiences' => $this->collectionLink('work-experiences', CandidateWorkExperience::query()->where('candidate_profile_id', $profile->getKey())->count()),
                'organizations' => $this->collectionLink('organizations', CandidateOrganization::query()->where('candidate_profile_id', $profile->getKey())->count()),
                'certifications' => $this->collectionLink('certifications', CandidateCertification::query()->where('candidate_profile_id', $profile->getKey())->count()),
                'links' => $this->collectionLink('links', CandidateLink::query()->where('candidate_profile_id', $profile->getKey())->count()),
                'skills' => $this->collectionLink('skills', CandidateSkill::query()->where('candidate_profile_id', $profile->getKey())->count()),
            ],
        ];

        foreach ($includes as $include) {
            $data[$include] = $this->collection($profile, $include);
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    public function collection(CandidateProfile $profile, string $collection): array
    {
        return match ($collection) {
            'educations' => CandidateEducation::query()->where('candidate_profile_id', $profile->getKey())->orderByDesc('start_date')->orderBy('id')->get()->map(fn (CandidateEducation $row): array => $this->education($row))->all(),
            'work_experiences' => CandidateWorkExperience::query()->where('candidate_profile_id', $profile->getKey())->orderByDesc('start_date')->orderBy('id')->get()->map(fn (CandidateWorkExperience $row): array => $this->workExperience($row))->all(),
            'organizations' => CandidateOrganization::query()->where('candidate_profile_id', $profile->getKey())->orderByDesc('start_date')->orderBy('id')->get()->map(fn (CandidateOrganization $row): array => $this->organization($row))->all(),
            'certifications' => CandidateCertification::query()->where('candidate_profile_id', $profile->getKey())->orderByDesc('issued_at')->orderBy('id')->get()->map(fn (CandidateCertification $row): array => $this->certification($row))->all(),
            'links' => CandidateLink::query()->where('candidate_profile_id', $profile->getKey())->orderBy('sort_order')->orderBy('id')->get()->map(fn (CandidateLink $row): array => $this->link($row))->all(),
            'skills' => CandidateSkill::query()->where('candidate_profile_id', $profile->getKey())->orderBy('id')->get()->map(fn (CandidateSkill $row): array => $this->skill($row))->all(),
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    public function verifications(CandidateProfile $profile): array
    {
        return CandidateVerification::query()->where('candidate_profile_id', $profile->getKey())
            ->orderBy('verification_type')->orderByDesc('updated_at')->get()
            ->map(fn (CandidateVerification $row): array => $this->verification($row))->all();
    }

    /** @return array<string, mixed> */
    public function profileAttributes(CandidateProfile $profile): array
    {
        return [
            'id' => (int) $profile->getKey(),
            'headline' => $profile->headline,
            'phone' => $profile->phone,
            'province_geographic_area_id' => $profile->province_geographic_area_id === null ? null : (int) $profile->province_geographic_area_id,
            'city_geographic_area_id' => $profile->city_geographic_area_id === null ? null : (int) $profile->city_geographic_area_id,
            'province' => $profile->province,
            'city' => $profile->city,
            'summary' => $profile->summary,
            'current_candidate_type' => $profile->current_candidate_type,
            'preferred_employment_type' => $profile->preferred_employment_type,
            'preferred_workplace_mode' => $profile->preferred_workplace_mode,
            'preferred_location_note' => $profile->preferred_location_note,
            'open_to_opportunities' => $profile->open_to_opportunities,
            'profile_completed_at' => $profile->profile_completed_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function education(CandidateEducation $row): array { return ['id' => (int) $row->getKey(), 'institution_name' => $row->institution_name, 'study_program_id' => $row->study_program_id === null ? null : (int) $row->study_program_id, 'study_program_name' => $row->study_program_name, 'education_level' => $row->education_level, 'start_date' => $row->start_date?->format('Y-m-d'), 'graduation_date' => $row->graduation_date?->format('Y-m-d'), 'graduation_year' => $row->graduation_year, 'score_summary' => $row->score_summary]; }
    /** @return array<string, mixed> */
    public function workExperience(CandidateWorkExperience $row): array { return ['id' => (int) $row->getKey(), 'employer_name' => $row->employer_name, 'position_title' => $row->position_title, 'employment_type' => $row->employment_type, 'start_date' => $row->start_date?->format('Y-m-d'), 'end_date' => $row->end_date?->format('Y-m-d'), 'is_current' => (bool) $row->is_current, 'description' => $row->description]; }
    /** @return array<string, mixed> */
    public function organization(CandidateOrganization $row): array { return ['id' => (int) $row->getKey(), 'organization_name' => $row->organization_name, 'role_title' => $row->role_title, 'organization_type' => $row->organization_type, 'start_date' => $row->start_date?->format('Y-m-d'), 'end_date' => $row->end_date?->format('Y-m-d'), 'is_current' => (bool) $row->is_current, 'description' => $row->description]; }
    /** @return array<string, mixed> */
    public function certification(CandidateCertification $row): array { return ['id' => (int) $row->getKey(), 'certification_name' => $row->certification_name, 'issuer_name' => $row->issuer_name, 'credential_identifier' => $row->credential_identifier, 'issued_at' => $row->issued_at?->format('Y-m-d'), 'expires_at' => $row->expires_at?->format('Y-m-d'), 'credential_url' => $row->credential_url, 'document_id' => $row->document_id === null ? null : (int) $row->document_id]; }
    /** @return array<string, mixed> */
    public function link(CandidateLink $row): array { return ['id' => (int) $row->getKey(), 'link_type' => $row->link_type, 'label' => $row->label, 'url' => $row->url, 'sort_order' => (int) $row->sort_order]; }
    /** @return array<string, mixed> */
    public function skill(CandidateSkill $row): array { return ['id' => (int) $row->getKey(), 'skill_id' => (int) $row->skill_id, 'proficiency_level' => $row->proficiency_level]; }
    /** @return array<string, mixed> */
    public function verification(CandidateVerification $row): array { return ['id' => (int) $row->getKey(), 'verification_type' => $row->verification_type, 'status' => $row->status, 'verified_at' => $row->verified_at?->toIso8601String(), 'rejection_reason' => $row->rejection_reason]; }

    /** @return array{count: int, href: string} */
    private function collectionLink(string $route, int $count): array { return ['count' => $count, 'href' => '/candidate/'.$route]; }
}
