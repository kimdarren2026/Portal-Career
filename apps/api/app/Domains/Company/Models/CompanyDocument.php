<?php

declare(strict_types=1);

namespace App\Domains\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyDocument extends Model
{
    protected $table = 'company_documents';
    /** @var list<string> Client-writable columns only; lifecycle stamps are set by Actions. */
    protected $fillable = ['company_id', 'document_type', 'document_number', 'issued_at', 'expires_at', 'storage_reference'];
    protected function casts(): array { return ['issued_at' => 'immutable_date', 'expires_at' => 'immutable_date', 'first_submitted_at' => 'immutable_datetime', 'superseded_at' => 'immutable_datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
