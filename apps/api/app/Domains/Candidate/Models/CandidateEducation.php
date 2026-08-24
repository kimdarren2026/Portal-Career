<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Models;

use App\Domains\MasterData\Models\StudyProgram;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CandidateEducation extends Model
{
    protected $table = 'candidate_educations';
    /** @var list<string> Client-writable columns only. */
    protected $fillable = [
        'institution_name',
        'study_program_id',
        'study_program_name',
        'education_level',
        'start_date',
        'graduation_date',
        'graduation_year',
        'score_summary',
    ];
    protected function casts(): array { return ['graduation_year' => 'integer', 'start_date' => 'immutable_date', 'graduation_date' => 'immutable_date', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime']; }
    public function candidateProfile(): BelongsTo { return $this->belongsTo(CandidateProfile::class); }
    public function studyProgram(): BelongsTo { return $this->belongsTo(StudyProgram::class); }
}
