<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Models;

use App\Domains\Vacancy\Enums\ScreeningQuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VacancyScreeningQuestion extends Model
{
    protected $table = 'vacancy_screening_questions';
    public $timestamps = false;

    /** @var list<string> `vacancy_id` is set by the owning Action, never by a client. */
    protected $fillable = ['question_text', 'question_type', 'required', 'options_definition', 'sort_order', 'active'];

    protected function casts(): array
    {
        return [
            'question_type' => ScreeningQuestionType::class,
            'options_definition' => 'array',
            'required' => 'boolean',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
}
