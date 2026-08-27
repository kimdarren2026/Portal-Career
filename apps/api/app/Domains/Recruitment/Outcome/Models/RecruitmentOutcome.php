<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Outcome\Models;

use App\Domains\Application\Models\Application;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recruitment outcome report (FSD §4.3 *Outcome Rekrutmen*). No `vacancy_id`
 * or `candidate_profile_id` column exists by design (decision D-4) — both are
 * reachable through the single populated source reference. `updated_at` does
 * not exist on this table; correction history lives only in the audit log
 * (`recruitment_outcome_updated`), never a row timestamp or history table.
 */
final class RecruitmentOutcome extends Model
{
    protected $table = 'recruitment_outcomes';

    public $timestamps = false;

    /** @var list<string> Every column here is server-resolved by the owning Action, never client-writable directly. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function confirmedBy(): BelongsTo { return $this->belongsTo(User::class, 'confirmed_by'); }
}
