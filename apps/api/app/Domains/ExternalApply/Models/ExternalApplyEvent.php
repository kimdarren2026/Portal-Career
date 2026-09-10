<?php

declare(strict_types=1);

namespace App\Domains\ExternalApply\Models;

use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `external_apply_events` — the separate event stream for candidates leaving
 * to an external ATS (FR-EXT-001..004). It is NOT an application: no row here
 * ever creates an `applications` record or sets a status to `APPLIED`
 * (INV-012), and INV-024 independently forbids an application on an
 * `EXTERNAL_ATS` vacancy. `event_type` is fixed to `EXTERNAL_APPLY_STARTED`
 * by `chk_external_apply_events_event_type`. `confirmation_status` starts at
 * `PENDING` and is only ever changed by an authorized confirmation source.
 */
final class ExternalApplyEvent extends Model
{
    public const EVENT_STARTED = 'EXTERNAL_APPLY_STARTED';
    public const STATUS_PENDING = 'PENDING';

    protected $table = 'external_apply_events';
    public $timestamps = false;

    /** @var list<string> Every column is server-resolved by the owning Action, never client-writable. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'candidate_profile_id' => 'integer',
            'vacancy_id' => 'integer',
            'confirmed_by' => 'integer',
            'consent_id' => 'integer',
            'started_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function candidateProfile(): BelongsTo { return $this->belongsTo(CandidateProfile::class); }
    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
    public function confirmedBy(): BelongsTo { return $this->belongsTo(User::class, 'confirmed_by'); }
}
