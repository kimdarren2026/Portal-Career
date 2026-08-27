<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Models;

use App\Domains\Application\Models\Application;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Offering\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An offering (FR-SEL-003). `document_reference` is never populated by Foundation v1 — see the create contract's amendment note. */
final class Offer extends Model
{
    protected $table = 'offers';

    /** @var list<string> Every column here is server-resolved by the owning Action, never client-writable directly. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'offered_at' => 'immutable_datetime',
            'response_deadline' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
            'offer_accepted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function offeredBy(): BelongsTo { return $this->belongsTo(User::class, 'offered_by_user_id'); }
}
