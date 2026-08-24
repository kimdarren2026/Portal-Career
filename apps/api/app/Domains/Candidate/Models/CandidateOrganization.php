<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CandidateOrganization extends Model
{
    protected $table = 'candidate_organizations';
    /** @var list<string> Client-writable columns only. */
    protected $fillable = [
        'organization_name',
        'role_title',
        'organization_type',
        'start_date',
        'end_date',
        'is_current',
        'description',
    ];
    protected function casts(): array { return ['start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'is_current' => 'boolean', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime']; }
    public function candidateProfile(): BelongsTo { return $this->belongsTo(CandidateProfile::class); }
}
