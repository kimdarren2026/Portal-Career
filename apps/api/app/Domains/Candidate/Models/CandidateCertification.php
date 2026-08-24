<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CandidateCertification extends Model
{
    protected $table = 'candidate_certifications';
    /** @var list<string> Client-writable columns only. */
    protected $fillable = [
        'certification_name',
        'issuer_name',
        'credential_identifier',
        'issued_at',
        'expires_at',
        'credential_url',
        'document_id',
    ];
    protected function casts(): array { return ['issued_at' => 'immutable_date', 'expires_at' => 'immutable_date', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime']; }
    public function candidateProfile(): BelongsTo { return $this->belongsTo(CandidateProfile::class); }
    public function document(): BelongsTo { return $this->belongsTo(CandidateDocument::class); }
}
