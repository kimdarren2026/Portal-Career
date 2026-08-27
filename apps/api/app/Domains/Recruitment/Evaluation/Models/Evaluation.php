<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Evaluation\Models;

use App\Domains\Application\Models\Application;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Models\RecruitmentStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A candidate evaluation (FR-SEL-002). Draft until `submitted_at` is set — there is no separate status column. */
final class Evaluation extends Model
{
    protected $table = 'evaluations';

    /** @var list<string> Every column here is server-resolved by the owning Action, never client-writable directly. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'total_score' => 'decimal:2',
            'submitted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function recruitmentStage(): BelongsTo { return $this->belongsTo(RecruitmentStage::class); }
    public function evaluator(): BelongsTo { return $this->belongsTo(User::class, 'evaluator_user_id'); }
    public function items(): HasMany { return $this->hasMany(EvaluationItem::class); }
}
