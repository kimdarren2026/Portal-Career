<?php

declare(strict_types=1);

namespace App\Domains\Application\Models;

use App\Domains\Vacancy\Models\VacancyScreeningQuestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One candidate response to an approved vacancy screening question (chk_application_screening_answers_one_value). */
final class ApplicationScreeningAnswer extends Model
{
    protected $table = 'application_screening_answers';
    public $timestamps = false;

    /** @var list<string> `application_id` and `screening_question_id` are set by the owning Action. */
    protected $fillable = ['answer_text', 'answer_boolean', 'answer_number', 'answer_option', 'answered_at'];

    protected function casts(): array
    {
        return [
            'answer_boolean' => 'boolean',
            'answer_number' => 'decimal:2',
            'answered_at' => 'immutable_datetime',
        ];
    }

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function screeningQuestion(): BelongsTo { return $this->belongsTo(VacancyScreeningQuestion::class, 'screening_question_id'); }
}
