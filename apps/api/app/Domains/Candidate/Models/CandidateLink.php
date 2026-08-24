<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CandidateLink extends Model
{
    protected $table = 'candidate_links';
    /** @var list<string> Client-writable columns only. */
    protected $fillable = [
        'link_type',
        'label',
        'url',
        'sort_order',
    ];
    protected function casts(): array { return ['sort_order' => 'integer', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime']; }
    public function candidateProfile(): BelongsTo { return $this->belongsTo(CandidateProfile::class); }
}
