<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Models;

use App\Domains\Application\Models\Application;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\SelectionSchedule\Enums\SelectionScheduleStatus;
use App\Domains\Vacancy\Models\RecruitmentStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A selection appointment (FR-SEL-001). `attachment_storage_reference` is never populated by Foundation v1 — see the create contract's amendment note. */
final class SelectionSchedule extends Model
{
    protected $table = 'selection_schedules';

    /** @var list<string> Every column here is server-resolved by the owning Action, never client-writable directly. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => SelectionScheduleStatus::class,
            'revision_number' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function recruitmentStage(): BelongsTo { return $this->belongsTo(RecruitmentStage::class); }
    public function pic(): BelongsTo { return $this->belongsTo(User::class, 'pic_user_id'); }
    public function histories(): HasMany { return $this->hasMany(SelectionScheduleHistory::class); }
}
