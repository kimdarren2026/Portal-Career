<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Models;

use App\Domains\MasterData\Models\Skill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CandidateSkill extends Model
{
    protected $table = 'candidate_skills';
    /** @var list<string> Client-writable columns only. */
    protected $fillable = [
        'skill_id',
        'proficiency_level',
    ];
    protected function casts(): array { return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime']; }
    public function candidateProfile(): BelongsTo { return $this->belongsTo(CandidateProfile::class); }
    public function skill(): BelongsTo { return $this->belongsTo(Skill::class); }
}
