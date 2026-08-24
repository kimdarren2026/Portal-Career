<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;

/**
 * End the current session and rotate the CSRF token.
 *
 * Invalidating rather than merely forgetting the user means the session record
 * in PostgreSQL is replaced, so a stolen cookie is useless afterwards.
 */
final class LogoutUser
{
    public function execute(): void
    {
        /** @var StatefulGuard $guard */
        $guard = Auth::guard('web');
        $guard->logout();

        if (app()->bound('session') && app('session')->isStarted()) {
            app('session')->invalidate();
            app('session')->regenerateToken();
        }
    }
}
