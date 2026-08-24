<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Candidate\Actions\UpdateCandidateProfile;
use App\Domains\Candidate\Queries\GetCandidateProfile;
use App\Domains\Candidate\Queries\GetCandidateVerifications;
use App\Domains\Candidate\Support\CandidateCollectionRegistry;
use App\Domains\Candidate\Support\CandidatePresenter;
use App\Http\Requests\Candidate\UpdateCandidateProfileRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET/PATCH /candidate/profile and the paired verification read (SPEC-DOC-07). */
final class CandidateProfileController extends CandidateController
{
    public function show(Request $request, GetCandidateProfile $query): JsonResponse
    {
        $profile = $this->ownProfile($request);
        $this->authorizeOrFail($request, 'view', $profile);

        return ContractResponse::success($request, $query->execute($profile, $this->include($request)));
    }

    public function update(UpdateCandidateProfileRequest $request, UpdateCandidateProfile $action): JsonResponse
    {
        $profile = $this->ownProfile($request);
        $this->authorizeOrFail($request, 'update', $profile);

        $updated = $action->execute($this->actor($request), $profile, $request->validated());

        return ContractResponse::success($request, CandidatePresenter::profile($updated));
    }

    public function verifications(Request $request, GetCandidateVerifications $query): JsonResponse
    {
        $profile = $this->ownProfile($request);
        $this->authorizeOrFail($request, 'view', $profile);

        return ContractResponse::success($request, ['items' => $query->execute($profile)]);
    }

    /** Allow-listed inline expansion only; unknown values are dropped, never queried. */
    /** @return list<string> */
    private function include(Request $request): array
    {
        $requested = array_filter(array_map('trim', explode(',', $request->string('include')->toString())));

        return array_values(array_intersect($requested, CandidateCollectionRegistry::slugs()));
    }
}
