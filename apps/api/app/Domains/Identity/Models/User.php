<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Support\EmailNormalizer;
use App\Domains\Candidate\Models\CandidateProfile;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;

/**
 * Global login identity (logical model 1.1-C3).
 *
 * Deliberately NOT Laravel's stock User model. Three differences matter:
 *
 *  1. There is no `password` column. The hash lives in `password_credentials`
 *     (see getAuthPassword()), so the credential can be rotated and audited
 *     independently of the profile row.
 *  2. There is no `remember_token` column, so remember-me is disabled rather
 *     than silently broken (getRememberTokenName() returns null).
 *  3. Authentication identity is `email_normalized`, never `email` (INV-001).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $email_normalized
 */
class User extends Model implements AuthenticatableContract
{
    use Authorizable;
    use Notifiable;

    protected $table = 'users';

    /**
     * Only presentation attributes are mass assignable. `status`,
     * `email_verified_at`, `disabled_at` and `anonymized_at` are lifecycle
     * fields changed through Actions, never through a request payload.
     */
    protected $fillable = [
        'name',
        'email',
        'email_normalized',
        'phone',
    ];

    protected $hidden = [
        'email_normalized',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'anonymized_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    // ---------------------------------------------------------------- auth --

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * The attribute the framework treats as the login identity. Used by
     * Laravel's rate limiter and logging, and it must be the normalized form.
     */
    public function getAuthIdentifierForBroadcasting(): string
    {
        return (string) $this->getKey();
    }

    /**
     * The password hash is stored in `password_credentials`, not on this row.
     * Returning it here keeps Laravel's EloquentUserProvider and Hash::check
     * working unchanged, without duplicating the hash onto `users`.
     */
    public function getAuthPassword(): string
    {
        return (string) ($this->passwordCredential?->password_hash ?? '');
    }

    public function getAuthPasswordName(): string
    {
        // No column on this table holds the password. Laravel only uses this
        // name for error attribution; the value comes from getAuthPassword().
        return 'password';
    }

    /**
     * Remember-me is unavailable: the frozen schema has no remember_token
     * column and this phase must not add one. Returning null makes the session
     * guard skip remember-token handling entirely.
     */
    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Intentionally a no-op — see getRememberTokenName().
    }

    // ----------------------------------------------------------- relations --

    public function passwordCredential(): HasOne
    {
        return $this->hasOne(PasswordCredential::class);
    }

    /** The candidate shell, when this identity was registered as a candidate. */
    public function candidateProfile(): HasOne
    {
        return $this->hasOne(CandidateProfile::class);
    }

    public function emailVerificationTokens(): HasMany
    {
        return $this->hasMany(EmailVerificationToken::class);
    }

    public function passwordResetTokens(): HasMany
    {
        return $this->hasMany(PasswordResetToken::class);
    }

    /** Every assignment ever made, including revoked ones (INV-025). */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /** Only assignments that currently grant anything. */
    public function activeUserRoles(): HasMany
    {
        return $this->userRoles()->whereNull('revoked_at');
    }

    // -------------------------------------------------------------- scopes --

    /** The only correct way to look up a login identity (INV-001). */
    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('email_normalized', EmailNormalizer::normalize($email));
    }

    // -------------------------------------------------------------- status --

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function canAuthenticate(): bool
    {
        return $this->status->canAuthenticate();
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Convenience only. Authorization decisions belong in Policies, and
     * eligibility decisions read candidate_verifications, never a role
     * (INV-028). Selector stage access additionally requires an active
     * assignment (INV-037) and is deliberately not answerable here.
     */
    public function hasActiveRole(RoleCode $code): bool
    {
        return $this->activeUserRoles()
            ->whereHas('role', fn (Builder $q) => $q->where('code', $code->value))
            ->exists();
    }
}
