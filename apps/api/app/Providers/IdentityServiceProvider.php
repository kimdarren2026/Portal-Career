<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Identity\Support\IdentityUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Identity domain's authentication integration.
 *
 * The `identity` user provider is a thin subclass of Laravel's Eloquent
 * provider that normalizes the email credential before lookup, so every
 * framework path resolves the same account for case-variant addresses
 * (INV-001) without the algorithm being duplicated in controllers.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::provider('identity', function ($app, array $config) {
            return new IdentityUserProvider($app['hash'], $config['model']);
        });
    }
}
