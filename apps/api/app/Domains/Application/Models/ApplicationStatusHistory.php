<?php

declare(strict_types=1);

namespace App\Domains\Application\Models;

use App\Domains\Application\Enums\ApplicationEventType;
use App\Domains\Application\Enums\ApplicationStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only application lifecycle history — authoritative (INV-026). Rows are never updated or deleted. */
final class ApplicationStatusHistory extends Model
{
    protected $table = 'application_status_histories';
    public $timestamps = false;

    /** @var list<string> `application_id` is set by the owning Action. */
    protected $fillable = [
        'from_status', 'to_status', 'from_stage_id', 'to_stage_id',
        'event_type', 'reason', 'candidate_visibility', 'candidate_visible_note', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => ApplicationStatus::class,
            'to_status' => ApplicationStatus::class,
            'event_type' => ApplicationEventType::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}
