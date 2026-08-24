<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Identity\Actions\LogoutUser;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Http\Responses\ContractResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Re-reads durable account status on every authenticated request. */
final class EnsureAccountStatus
{
    public function __construct(private readonly LogoutUser $logout) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        /** @var User|null $fresh */
        $fresh = User::query()->find($user->getKey());
        if ($fresh === null || $fresh->status === UserStatus::Suspended || $fresh->status === UserStatus::Disabled) {
            $this->logout->execute();
            $code = $fresh?->status === UserStatus::Disabled ? 'AUTH_ACCOUNT_DISABLED' : 'AUTH_ACCOUNT_SUSPENDED';
            $message = $code === 'AUTH_ACCOUNT_DISABLED'
                ? 'Akun ini telah dinonaktifkan.'
                : 'Akun ini sedang ditangguhkan.';

            return ContractResponse::error($request, $code, 403, $message);
        }

        $request->setUserResolver(fn (): User => $fresh);

        return $next($request);
    }
}
