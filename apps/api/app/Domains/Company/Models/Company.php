<?php

declare(strict_types=1);

namespace App\Domains\Company\Models;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Company extends Model
{
    protected $table = 'companies';

    protected $fillable = [
        'name', 'normalized_name', 'organization_type_id', 'industry_id', 'website',
        'official_email', 'official_phone', 'address', 'province_geographic_area_id',
        'city_geographic_area_id', 'legal_identifier', 'logo_storage_reference',
    ];

    protected function casts(): array
    {
        return [
            'verification_status' => CompanyStatus::class,
            'verified_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function members(): HasMany { return $this->hasMany(CompanyMember::class); }
    public function documents(): HasMany { return $this->hasMany(CompanyDocument::class); }
    public function verificationReviews(): HasMany { return $this->hasMany(CompanyVerificationReview::class); }

    public function isVerified(): bool { return $this->verification_status === CompanyStatus::Verified; }
}
