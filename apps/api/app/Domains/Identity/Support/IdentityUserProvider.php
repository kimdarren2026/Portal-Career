<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Eloquent user provider that resolves login identity through
 * `email_normalized` (INV-001).
 *
 * Laravel's stock provider builds its lookup from the raw credentials array,
 * which would query `users.email` — a column that deliberately carries no
 * uniqueness constraint. Normalizing here means every framework path
 * (Auth::attempt, the password broker, rate limiting) resolves the same
 * identity for `A@B.com` and `a@b.com`, without the algorithm being duplicated
 * in controllers.
 *
 * The password hash itself still comes from User::getAuthPassword(), which
 * reads `password_credentials` — so validateCredentials() is inherited
 * unchanged and continues to use the Hash service.
 */
final class IdentityUserProvider extends EloquentUserProvider
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (isset($credentials['email']) && is_string($credentials['email'])) {
            $credentials['email_normalized'] = EmailNormalizer::normalize($credentials['email']);
            unset($credentials['email']);
        }

        return parent::retrieveByCredentials($credentials);
    }
}
