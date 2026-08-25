<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Models;

use App\Domains\MasterData\Models\Skill;
use App\Domains\MasterData\Models\StudyProgram;
use App\Domains\Vacancy\Enums\RequirementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VacancyRequirement extends Model
{
    protected $table = 'vacancy_requirements';
    public $timestamps = false;

    /** @var list<string> `vacancy_id` is set by the owning Action, never by a client. */
    protected $fillable = [
        'requirement_type', 'education_level', 'study_program_id', 'skill_id',
        'minimum_years_experience', 'value_text', 'note', 'required', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requirement_type' => RequirementType::class,
            'minimum_years_experience' => 'integer',
            'required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
    public function studyProgram(): BelongsTo { return $this->belongsTo(StudyProgram::class); }
    public function skill(): BelongsTo { return $this->belongsTo(Skill::class); }
}
