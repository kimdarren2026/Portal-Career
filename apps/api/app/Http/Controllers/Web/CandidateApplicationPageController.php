<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Exceptions\ApplicationNotFound;
use App\Domains\Application\Queries\GetCandidateApplication;
use App\Domains\Application\Queries\ListCandidateApplications;
use App\Domains\Application\Support\ApplicationScope;
use App\Domains\Candidate\Queries\ListCandidateDocuments;
use App\Domains\Candidate\Support\CandidateProfileResolver;
use App\Domains\Recruitment\Offering\Models\Offer;
use App\Domains\Recruitment\Offering\Presenters\OfferPresenter;
use App\Domains\Vacancy\Support\PublicVacancyPresenter;
use App\Domains\Vacancy\Support\PublicVacancyScope;
use App\Domains\Vacancy\Support\VacancyPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Non-mutating Inertia delivery for the candidate's own application journey
 * (Frontend Vertical Slice v1) — same "page controller" precedent as
 * `CandidatePageController`. Reuses the frozen Candidate Application
 * queries/scopes directly (never a loopback HTTP call to the JSON
 * endpoints); every mutation (submit, withdraw) still posts to the existing
 * `ApplicationController` routes from the client.
 */
final class CandidateApplicationPageController extends CandidateController
{
    public function __construct(
        CandidateProfileResolver $profiles,
        private readonly ApplicationScope $scope,
    ) {
        parent::__construct($profiles);
    }

    /** `GET /lamaran-saya`. */
    public function index(Request $request, ListCandidateApplications $query): Response
    {
        $actor = $this->actor($request);
        $filters = array_intersect_key($request->query(), array_flip(ListCandidateApplications::FILTERS));
        $applications = $query->execute(
            $actor,
            $filters,
            $request->string('sort', 'first_applied_at')->toString(),
            $request->string('direction', 'desc')->toString(),
        );

        $items = $applications->items();
        $vacancyTitles = self::vacancyTitles(array_column($items, 'vacancy_id'));
        foreach ($items as &$item) {
            $item['vacancy_title'] = $vacancyTitles[$item['vacancy_id']] ?? null;
        }
        unset($item);

        return Inertia::render('candidate/LamaranSaya', [
            'items' => $items,
            'pagination' => [
                'page' => $applications->currentPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
                'last_page' => $applications->lastPage(),
            ],
            'filters' => (object) $filters,
        ]);
    }

    /** `GET /lamaran-saya/{application}`. */
    public function show(Request $request, int $application, GetCandidateApplication $query): Response
    {
        $actor = $this->actor($request);
        $model = $this->scope->findFor($actor, $application) ?? throw new ApplicationNotFound();

        $detail = $query->execute($model, GetCandidateApplication::INCLUDES);
        $detail['vacancy_title'] = self::vacancyTitles([$detail['vacancy_id']])[$detail['vacancy_id']] ?? null;

        // Offers become candidate-visible only once the recruiter has sent them
        // (FR-SEL-003 — DRAFT is a recruiter-only working state). `$model` is
        // already this candidate's own application, so any offer on it is
        // theirs; `OfferPresenter::candidate` is the frozen candidate allow-list
        // (no `offered_by_user_id`, no `rejection_reason`, no internal notes).
        $offers = Offer::query()
            ->where('application_id', $model->getKey())
            ->whereNotNull('sent_at')
            ->orderBy('created_at', 'desc')->orderBy('id', 'desc')->get()
            ->map(OfferPresenter::candidate(...))->values()->all();

        return Inertia::render('candidate/ApplicationDetail', [
            'application' => $detail,
            'offers' => $offers,
        ]);
    }

    /**
     * `GET /lowongan/{slug}/lamar` — the apply form for an authenticated
     * candidate. Resolves the vacancy through the identical public
     * visibility predicate (`PublicVacancyScope`) the anonymous discovery
     * pages already use — a vacancy invisible to `/lowongan` is invisible
     * here too, never a second, looser rule. `EXTERNAL_ATS` vacancies never
     * reach the internal apply form (FR-EXT-001) — External Apply itself has
     * no routed runtime yet in this codebase, so that state is surfaced as
     * "not yet available", not silently faked.
     */
    public function apply(Request $request, string $slug, ListCandidateDocuments $documentsQuery): Response|SymfonyResponse
    {
        $vacancy = PublicVacancyScope::query()->with('company')->where('slug', $slug)->first();
        if ($vacancy === null) {
            if ($request->expectsJson()) {
                abort(404);
            }

            return Inertia::render('Error', ['status' => 404])->toResponse($request)->setStatusCode(404);
        }

        $detail = PublicVacancyPresenter::detail($vacancy);
        $questions = $vacancy->screeningQuestions()->where('active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->get()->map(VacancyPresenter::screeningQuestion(...))->values()->all();

        $profile = $this->ownProfile($request);
        $existing = DB::table('applications')
            ->where('vacancy_id', $vacancy->getKey())
            ->where('candidate_profile_id', $profile->getKey())
            ->value('id');

        $documents = $documentsQuery->execute($profile)->items();

        return Inertia::render('candidate/ApplyForm', [
            'vacancy' => array_merge($detail, ['id' => (int) $vacancy->getKey()]),
            'screening_questions' => $questions,
            'existing_application_id' => $existing !== null ? (int) $existing : null,
            'documents' => array_map(fn ($document): array => [
                'id' => (int) $document->getKey(),
                'display_name' => $document->display_name,
                'document_type' => $document->document_type,
            ], $documents),
        ]);
    }

    /** @param list<int|null> $vacancyIds @return array<int, string> */
    private static function vacancyTitles(array $vacancyIds): array
    {
        $ids = array_values(array_unique(array_filter($vacancyIds, fn ($id): bool => $id !== null)));
        if ($ids === []) {
            return [];
        }

        return DB::table('vacancies')->whereIn('id', $ids)->pluck('title', 'id')->all();
    }
}
