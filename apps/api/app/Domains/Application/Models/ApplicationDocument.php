<?php

declare(strict_types=1);

namespace App\Domains\Application\Models;

use App\Domains\Candidate\Models\CandidateDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intentional document sharing for one application. Snapshot fields are
 * captured at share time (INV-032) so later editing, replacement, or
 * archival of the candidate's private source document can never silently
 * alter historical recruitment evidence.
 */
final class ApplicationDocument extends Model
{
    protected $table = 'application_documents';
    public $timestamps = false;

    /** @var list<string> `application_id` and `candidate_document_id` are set by the owning Action. */
    protected $fillable = ['shared_at', 'snapshot_name', 'snapshot_storage_reference', 'snapshot_checksum', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'shared_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function candidateDocument(): BelongsTo { return $this->belongsTo(CandidateDocument::class); }
}
