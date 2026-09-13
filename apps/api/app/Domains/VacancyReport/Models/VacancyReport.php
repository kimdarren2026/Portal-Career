<?php

declare(strict_types=1);

namespace App\Domains\VacancyReport\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "Laporkan Lowongan" anti-fraud report against a published vacancy
 * (PGC-V1 / PD-C). Reporter identity is optional: an anonymous reporter is
 * permitted; an authenticated reporter's `reporter_user_id` is attached
 * server-side and the reporter's identity is never exposed publicly.
 * Lifecycle NEW -> UNDER_REVIEW -> ACTIONED | DISMISSED, Career Center owned.
 */
final class VacancyReport extends Model
{
    protected $table = 'vacancy_reports';

    /** @var list<string> Every column is server-resolved by the owning Action. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'review_started_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_user_id'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by_user_id'); }
}
