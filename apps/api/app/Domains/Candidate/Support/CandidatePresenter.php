<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Support;

use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Candidate\Models\CandidateVerification;
use Illuminate\Database\Eloquent\Model;

/** Read-model shaping. Storage references never leave the server (FR-CAN-005). */
final class CandidatePresenter
{
    /** @return array<string, mixed> */
    public static function profile(CandidateProfile $profile): array
    {
        return [
            'id' => (int) $profile->getKey(),
            'headline' => $profile->headline,
            'phone' => $profile->phone,
            'summary' => $profile->summary,
            'province_geographic_area_id' => self::nullableInt($profile->province_geographic_area_id),
            'city_geographic_area_id' => self::nullableInt($profile->city_geographic_area_id),
            'province' => $profile->province,
            'city' => $profile->city,
            'preferred_employment_type' => $profile->preferred_employment_type,
            'preferred_workplace_mode' => $profile->preferred_workplace_mode,
            'preferred_location_note' => $profile->preferred_location_note,
            'open_to_opportunities' => $profile->open_to_opportunities,
            'current_candidate_type' => $profile->current_candidate_type,
            // DEFERRED POLICY: returned as stored, never computed (Part X item 8).
            'profile_completed_at' => $profile->profile_completed_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function verification(CandidateVerification $verification): array
    {
        return [
            'id' => (int) $verification->getKey(),
            'verification_type' => $verification->verification_type,
            'status' => $verification->status,
            'verified_at' => $verification->verified_at?->toIso8601String(),
            'created_at' => $verification->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function document(CandidateDocument $document, bool $sharedWithApplication): array
    {
        return [
            'id' => (int) $document->getKey(),
            'document_type' => $document->document_type,
            'display_name' => $document->display_name,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'uploaded_at' => $document->uploaded_at?->toIso8601String(),
            'archived_at' => $document->archived_at?->toIso8601String(),
            'shared_with_application' => $sharedWithApplication,
        ];
    }

    /** Collection rows are the candidate's own data; every column but the parent key is returned. */
    /** @return array<string, mixed> */
    public static function collectionRow(Model $row): array
    {
        $attributes = $row->attributesToArray();
        unset($attributes['candidate_profile_id']);
        $attributes['id'] = (int) $row->getKey();

        return $attributes;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
