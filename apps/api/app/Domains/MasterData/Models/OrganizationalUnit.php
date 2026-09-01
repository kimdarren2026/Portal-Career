<?php

declare(strict_types=1);

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Campus organizational unit — faculty / department / division (FR-HR-002
 * "unit/fakultas/bagian"). Read-only master data: the set of units is
 * institutional configuration, not a closed enum, and is never authored
 * through the application. A campus vacancy references one active unit
 * (`vacancies.organizational_unit_id`, ownership XOR — INV-018).
 */
final class OrganizationalUnit extends Model
{
    protected $table = 'organizational_units';

    /** @var list<string> Read-only master data. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_unit_id');
    }
}
