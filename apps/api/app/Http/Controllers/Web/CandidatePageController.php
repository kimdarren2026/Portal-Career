<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Candidate\Queries\GetCandidateCollection;
use App\Domains\Candidate\Queries\GetCandidateProfile;
use App\Domains\Candidate\Queries\GetCandidateVerifications;
use App\Domains\Candidate\Queries\ListCandidateDocuments;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;

/** Non-mutating Inertia delivery for the candidate core profile workspace. */
final class CandidatePageController extends CandidateController
{
    public function profile(
        Request $request,
        GetCandidateProfile $profileQuery,
        GetCandidateCollection $collectionQuery,
        GetCandidateVerifications $verificationQuery,
        ListCandidateDocuments $documentQuery,
    ): Response {
        $profile = $this->ownProfile($request);
        $this->authorizeOrFail($request, 'view', $profile);

        $collections = [];
        foreach (GetCandidateProfile::includableSlugs() as $slug) {
            $collections[$slug] = $collectionQuery->execute($profile, $slug);
        }
        $documents = $documentQuery->execute($profile, includeArchived: true);

        return Inertia::render('candidate/Profile', [
            'profile' => $profileQuery->execute($profile),
            'collections' => $collections,
            'verifications' => $verificationQuery->execute($profile),
            'documents' => [
                'items' => $documents->items(),
                'pagination' => ['page' => $documents->currentPage(), 'total' => $documents->total()],
            ],
        ]);
    }
}
