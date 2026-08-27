<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Evaluation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One scored/commented criterion within an evaluation. No `created_at`/`updated_at` — the schema carries none. */
final class EvaluationItem extends Model
{
    protected $table = 'evaluation_items';
    public $timestamps = false;

    /** @var list<string> `evaluation_id` is set by the owning Action, never by a client. */
    protected $fillable = ['criterion', 'weight', 'score', 'comment', 'sort_order'];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'score' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function evaluation(): BelongsTo { return $this->belongsTo(Evaluation::class); }
}
