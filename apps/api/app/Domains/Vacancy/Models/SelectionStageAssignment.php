<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Binds one active SELECTOR to one vacancy-specific recruitment stage
 * (FR-HR-006, INV-037). Holding the SELECTOR role is only a precondition for
 * being assigned — a row here is what actually grants stage scope, and only
 * while `revoked_at` is null. Revocation never deletes the row; history is
 * append-only.
 */
final class SelectionStageAssignment extends Model
{
    protected $table = 'selection_stage_assignments';

    /** @var list<string> Every column is server-resolved by the owning Action, never client-writable. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'recruitment_stage_id' => 'integer',
            'selector_user_id' => 'integer',
            'assigned_by_user_id' => 'integer',
            'revoked_by_user_id' => 'integer',
            'assigned_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function stage(): BelongsTo { return $this->belongsTo(RecruitmentStage::class, 'recruitment_stage_id'); }
    public function selector(): BelongsTo { return $this->belongsTo(User::class, 'selector_user_id'); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by_user_id'); }
}
