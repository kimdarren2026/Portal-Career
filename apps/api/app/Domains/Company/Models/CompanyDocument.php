<?php

declare(strict_types=1);

namespace App\Domains\Company\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company legal document (FR-ONB-002). Once `first_submitted_at` is set the
 * row is verification evidence and is never destructively deleted — it is
 * replaced through `superseded_by_document_id` (INV-038). The upload metadata
 * columns are set by `UploadCompanyDocument` (PGC-V1 / PD-D).
 */
final class CompanyDocument extends Model
{
    protected $table = 'company_documents';

    /** @var list<string> Every column is server-resolved by the owning Action, never client-writable. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_date',
            'expires_at' => 'immutable_date',
            'first_submitted_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
            'file_size_bytes' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by_user_id'); }
    public function supersededBy(): BelongsTo { return $this->belongsTo(self::class, 'superseded_by_document_id'); }

    /** A DRAFT document (never part of a submitted verification package) may be deleted outright (Q-2). */
    public function isDraft(): bool
    {
        return $this->first_submitted_at === null;
    }
}
