<?php

declare(strict_types=1);

namespace App\Domains\Application\Models;

use App\Domains\Application\Enums\ApplicationStatus;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The single lifecycle record for one candidate and one in-portal vacancy
 * (INV-007). `current_status`, `current_stage_id`, `reopen_count`, and
 * `last_reopened_at` are derived caches of `application_status_histories`,
 * which is authoritative (INV-026) — this model never computes them by
 * scanning history; the writing Action keeps cache and history in the same
 * transaction.
 */
final class Application extends Model
{
    protected $table = 'applications';

    /** @var list<string> Every column here is server-resolved by the owning Action, never client-writable. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'current_status' => ApplicationStatus::class,
            'first_applied_at' => 'immutable_datetime',
            'last_reopened_at' => 'immutable_datetime',
            'reopen_count' => 'integer',
            'withdrawn_at' => 'immutable_datetime',
            'hired_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function candidateProfile(): BelongsTo { return $this->belongsTo(CandidateProfile::class); }
    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
    public function statusHistories(): HasMany { return $this->hasMany(ApplicationStatusHistory::class); }
    public function documents(): HasMany { return $this->hasMany(ApplicationDocument::class); }
    public function screeningAnswers(): HasMany { return $this->hasMany(ApplicationScreeningAnswer::class); }
}
