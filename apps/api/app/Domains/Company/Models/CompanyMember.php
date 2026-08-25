<?php

declare(strict_types=1);

namespace App\Domains\Company\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyMember extends Model
{
    protected $table = 'company_members';
    public $timestamps = false;
    protected $fillable = ['company_id', 'user_id', 'company_role', 'status', 'joined_at', 'invited_by'];
    protected function casts(): array { return ['joined_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function scopeActive($query) { return $query->where('status', 'ACTIVE')->whereNull('revoked_at'); }
    public function isActive(): bool { return $this->status === 'ACTIVE' && $this->revoked_at === null; }
}
