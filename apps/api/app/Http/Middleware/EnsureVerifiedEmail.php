<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Http\Responses\ContractResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Reusable verified-email gate for future protected business operations. */
final class EnsureVerifiedEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user !== null && $user->status === UserStatus::PendingEmailVerification) {
            return ContractResponse::error(
                $request,
                'AUTH_EMAIL_NOT_VERIFIED',
                403,
                'Verifikasi email diperlukan untuk melanjutkan.',
            );
        }

        return $next($request);
    }
}
