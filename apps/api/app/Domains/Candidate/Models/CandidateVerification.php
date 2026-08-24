<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Models;

use App\Domains\MasterData\Models\StudyProgram;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CandidateVerification extends Model
{
    protected $table = 'candidate_verifications';
    /** @var list<string> Client-writable columns only. */
    protected $fillable = [
        'verification_type',
        'student_number',
        'program_study_id',
        'graduation_year',
    ];

    protected function casts(): array
    {
        return [
            'graduation_year' => 'integer',
            'verified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function candidateProfile(): BelongsTo { return $this->belongsTo(CandidateProfile::class); }
    public function studyProgram(): BelongsTo { return $this->belongsTo(StudyProgram::class, 'program_study_id'); }
}
