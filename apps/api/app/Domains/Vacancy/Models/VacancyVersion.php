<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only vacancy revision snapshot (FR-VAC-007, INV-016). Never updated, never deleted. */
final class VacancyVersion extends Model
{
    protected $table = 'vacancy_versions';
    public $timestamps = false;

    /** @var list<string> Written only by an Action; no client input reaches this model. */
    protected $fillable = ['vacancy_id', 'version_number', 'snapshot', 'created_by', 'change_reason', 'created_at'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'version_number' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
