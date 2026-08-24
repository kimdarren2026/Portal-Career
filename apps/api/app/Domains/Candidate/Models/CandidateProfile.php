<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The single, durable candidate shell created during candidate registration. */
final class CandidateProfile extends Model
{
    protected $table = 'candidate_profiles';

    /** @var list<string> Client-writable columns only. */
    protected $fillable = [
        'headline',
        'phone',
        'province_geographic_area_id',
        'city_geographic_area_id',
        'city',
        'province',
        'summary',
        'preferred_employment_type',
        'preferred_workplace_mode',
        'preferred_location_note',
        'open_to_opportunities',
    ];

    protected function casts(): array
    {
        return [
            'open_to_opportunities' => 'boolean',
            'profile_completed_at' => 'immutable_datetime',
            'anonymized_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function verifications(): HasMany { return $this->hasMany(CandidateVerification::class); }
    public function documents(): HasMany { return $this->hasMany(CandidateDocument::class); }
    public function educations(): HasMany { return $this->hasMany(CandidateEducation::class); }
    public function workExperiences(): HasMany { return $this->hasMany(CandidateWorkExperience::class); }
    public function organizations(): HasMany { return $this->hasMany(CandidateOrganization::class); }
    public function skills(): HasMany { return $this->hasMany(CandidateSkill::class); }
    public function certifications(): HasMany { return $this->hasMany(CandidateCertification::class); }
    public function links(): HasMany { return $this->hasMany(CandidateLink::class); }
}
