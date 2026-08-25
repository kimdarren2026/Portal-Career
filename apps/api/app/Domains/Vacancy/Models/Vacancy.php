<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Models;

use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Enums\ApplicationMethod;
use App\Domains\Vacancy\Enums\TargetAudience;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Enums\VacancyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Vacancy extends Model
{
    protected $table = 'vacancies';

    /**
     * @var list<string> Client-writable attributes only.
     *
     * Ownership, identity, lifecycle and moderation columns are deliberately
     * absent: `vacancy_code`, `slug`, `ownership_type`, `company_id`,
     * `organizational_unit_id`, `created_by`, `current_status`, `published_at`,
     * `closed_at`, `suspended_at` are server-resolved and written only by an
     * Action through forceFill().
     */
    protected $fillable = [
        'vacancy_type',
        'title',
        'description',
        'responsibilities',
        'employment_type',
        'workplace_mode',
        'province_geographic_area_id',
        'city_geographic_area_id',
        'location',
        'openings_count',
        'minimum_education',
        'experience_requirement',
        'salary_min',
        'salary_max',
        'salary_currency',
        'target_audience',
        'application_method',
        'external_ats_url',
        'open_at',
        'close_at',
    ];

    protected function casts(): array
    {
        return [
            'vacancy_type' => VacancyType::class,
            'target_audience' => TargetAudience::class,
            'application_method' => ApplicationMethod::class,
            'current_status' => VacancyStatus::class,
            'openings_count' => 'integer',
            'open_at' => 'immutable_datetime',
            'close_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function requirements(): HasMany { return $this->hasMany(VacancyRequirement::class); }
    public function screeningQuestions(): HasMany { return $this->hasMany(VacancyScreeningQuestion::class); }
    public function versions(): HasMany { return $this->hasMany(VacancyVersion::class); }
    public function moderationReviews(): HasMany { return $this->hasMany(VacancyModerationReview::class); }

    public function isCompanyOwned(): bool
    {
        return $this->ownership_type === 'COMPANY';
    }
}
