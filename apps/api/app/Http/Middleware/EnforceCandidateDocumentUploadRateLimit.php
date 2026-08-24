<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Candidate\Support\CandidateDocumentUploadLimiter;
use App\Domains\Identity\Models\User;
use App\Http\Responses\ContractResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceCandidateDocumentUploadRateLimit
{
    public function __construct(private readonly CandidateDocumentUploadLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $actor */
        $actor = $request->user();
        if (! $actor instanceof User) {
            return $next($request);
        }

        $rate = $this->limiter->checkAndRecord($actor);
        if ($rate['blocked']) {
            return ContractResponse::error(
                $request,
                'RATE_LIMITED',
                429,
                'Terlalu banyak unggahan. Silakan coba kembali nanti.',
                retryAfter: $rate['retry_after'],
            );
        }

        return $next($request);
    }
}
