<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Enums\VacancyModerationAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only moderation history (INV-016). Rows are never updated or deleted. */
final class VacancyModerationReview extends Model
{
    protected $table = 'vacancy_moderation_reviews';
    public $timestamps = false;

    /** @var list<string> `vacancy_id` and `reviewer_user_id` are set by the owning Action. */
    protected $fillable = [
        'action', 'from_status', 'to_status',
        'reason_category', 'recruiter_visible_note', 'internal_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['action' => VacancyModerationAction::class, 'reviewed_at' => 'immutable_datetime'];
    }

    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_user_id'); }
}
