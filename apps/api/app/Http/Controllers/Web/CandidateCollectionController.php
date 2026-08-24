<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Candidate\Actions\SyncCandidateCollection;
use App\Domains\Candidate\Actions\SyncCandidateCertifications;
use App\Domains\Candidate\Actions\SyncCandidateEducations;
use App\Domains\Candidate\Actions\SyncCandidateLinks;
use App\Domains\Candidate\Actions\SyncCandidateOrganizations;
use App\Domains\Candidate\Actions\SyncCandidateSkills;
use App\Domains\Candidate\Actions\SyncCandidateWorkExperiences;
use App\Domains\Candidate\Queries\GetCandidateCollection;
use App\Domains\Candidate\Support\CandidateCollectionRegistry;
use App\Http\Requests\Candidate\CandidateCollectionRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET/PUT /candidate/{collection} — the grouped contract over the six repeatable
 * profile sections. The collection slug is bound by the route, so only registered
 * slugs ever reach the registry.
 */
final class CandidateCollectionController extends CandidateController
{
    public function index(Request $request, string $collection, GetCandidateCollection $query): JsonResponse
    {
        $profile = $this->ownProfile($request);
        $this->authorizeOrFail($request, 'view', $profile);

        return ContractResponse::success($request, ['items' => $query->execute($profile, $collection)]);
    }

    public function sync(
        Request $request,
        string $collection,
        GetCandidateCollection $query,
    ): JsonResponse {
        $profile = $this->ownProfile($request);
        $this->authorizeOrFail($request, 'manage', $profile);

        // Per-collection Form Request: every row is validated before anything is written.
        /** @var CandidateCollectionRequest $validated */
        $validated = app(CandidateCollectionRegistry::requestFor($collection));

        try {
            $this->actionFor($collection)->execute(
                $this->actor($request),
                $profile,
                $collection,
                array_values((array) $validated->validated()['items']),
            );
        } catch (ModelNotFoundException) {
            return ContractResponse::error(
                $request,
                'NOT_FOUND',
                404,
                'Salah satu baris yang dikirim bukan milik koleksi ini.',
            );
        }

        return ContractResponse::success($request, ['items' => $query->execute($profile, $collection)]);
    }

    private function actionFor(string $collection): SyncCandidateCollection
    {
        return app(match ($collection) {
            'educations' => SyncCandidateEducations::class,
            'work-experiences' => SyncCandidateWorkExperiences::class,
            'organizations' => SyncCandidateOrganizations::class,
            'certifications' => SyncCandidateCertifications::class,
            'links' => SyncCandidateLinks::class,
            'skills' => SyncCandidateSkills::class,
        });
    }
}
