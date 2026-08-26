<?php

declare(strict_types=1);

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

final class OrganizationType extends Model
{
    protected $table = 'organization_types';
    /** @var list<string> Read-only master data. */
    protected $fillable = [];
    protected function casts(): array { return ['active' => 'boolean', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime']; }
}
