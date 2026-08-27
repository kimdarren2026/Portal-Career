<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\SelectionSchedule\Enums\SelectionScheduleEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only schedule change history (INV-016). Rows are never updated or deleted. */
final class SelectionScheduleHistory extends Model
{
    protected $table = 'selection_schedule_histories';
    public $timestamps = false;

    /** @var list<string> `selection_schedule_id` is set by the owning Action. */
    protected $fillable = [
        'event_type', 'previous_snapshot', 'resulting_revision_number', 'reason', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => SelectionScheduleEventType::class,
            'previous_snapshot' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function schedule(): BelongsTo { return $this->belongsTo(SelectionSchedule::class, 'selection_schedule_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}
