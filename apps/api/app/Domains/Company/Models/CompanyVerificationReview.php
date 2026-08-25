<?php

declare(strict_types=1);

namespace App\Domains\Company\Models;

use App\Domains\Company\Enums\CompanyReviewAction;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyVerificationReview extends Model
{
    protected $table = 'company_verification_reviews';
    public $timestamps = false;
    /** @var list<string> Append-only review row written by review Actions (INV-016). */
    protected $fillable = ['company_id', 'reviewer_user_id', 'action', 'from_status', 'to_status', 'reason_category', 'recruiter_visible_note', 'internal_note', 'reviewed_at'];
    protected function casts(): array { return ['action' => CompanyReviewAction::class, 'reviewed_at' => 'immutable_datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_user_id'); }
}
