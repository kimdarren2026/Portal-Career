<?php

declare(strict_types=1);

namespace App\Domains\Consent\Models;

use App\Domains\Application\Models\Application;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Independently auditable consent (INV-011) — never a Boolean field on
 * `applications`. Exactly one receiving party is identifiable, determined by
 * the vacancy's ownership (INV-023); neither-present and both-present are
 * invalid (`chk_consents_receiver_xor` enforces "not both" at the database;
 * "neither" is a service-layer check).
 */
final class Consent extends Model
{
    protected $table = 'consents';
    public $timestamps = false;

    /** @var list<string> Every column is server-derived by the owning Action, never client-writable. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'consented_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
    public function receivingCompany(): BelongsTo { return $this->belongsTo(Company::class, 'receiving_company_id'); }
}
