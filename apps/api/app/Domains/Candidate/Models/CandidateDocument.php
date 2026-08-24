<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CandidateDocument extends Model
{
    protected $table = 'candidate_documents';
    /** @var list<string> Client-writable columns only. */
    protected $fillable = [
        'document_type',
        'display_name',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'uploaded_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function candidateProfile(): BelongsTo { return $this->belongsTo(CandidateProfile::class); }
}
