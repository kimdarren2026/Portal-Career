<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Configurable, vacancy-specific recruitment stages (DATA_DICTIONARY.md). No `updated_at` — the schema carries only `created_at`. */
final class RecruitmentStage extends Model
{
    protected $table = 'recruitment_stages';
    public $timestamps = false;

    /** @var list<string> `vacancy_id` is set by the owning Action, never by a client. */
    protected $fillable = ['name', 'stage_type', 'sort_order', 'active', 'candidate_visible_label'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
}
