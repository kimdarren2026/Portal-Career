<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Candidate\Support\CandidateProfileResolver;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Shared actor/profile/authorization plumbing for the Candidate Core surface. */
abstract class CandidateController extends Controller
{
    public function __construct(protected readonly CandidateProfileResolver $profiles) {}

    protected function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    protected function ownProfile(Request $request): CandidateProfile
    {
        return $this->profiles->forActor($this->actor($request));
    }

    /** Fail fast on a Policy denial with the contract's own code, not a generic 403 page. */
    protected function authorizeOrFail(Request $request, string $ability, object $subject, string $code = 'AUTH_FORBIDDEN'): void
    {
        if (Gate::forUser($this->actor($request))->denies($ability, $subject)) {
            throw new HttpResponseException(ContractResponse::error(
                $request,
                $code,
                403,
                'Anda tidak berhak melakukan tindakan ini.',
            ));
        }
    }
}
