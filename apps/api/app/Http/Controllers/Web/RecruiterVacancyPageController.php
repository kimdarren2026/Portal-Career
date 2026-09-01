<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Support\CompanyScope;
use App\Domains\Identity\Models\User;
use App\Domains\MasterData\Queries\GetPublicReferenceData;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Enums\VacancyType;
use App\Domains\Vacancy\Exceptions\VacancyNotFound;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Queries\GetVacancy;
use App\Domains\Vacancy\Queries\ListVacancies;
use App\Domains\Vacancy\Support\VacancyScope;
use App\Http\Controllers\Controller;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Non-mutating Inertia delivery for the recruiter "Kelola Lowongan" pages
 * (Recruiter Company & Vacancy Frontend Slice v3): owner list, create form,
 * detail/edit.
 *
 * Reads reuse the frozen `ListVacancies` / `GetVacancy` / `VacancyScope`
 * classes directly — company-scoped in the query, never filtered after fetch,
 * and never trusting a browser-supplied `company_id`. A vacancy outside scope
 * is absent from `VacancyScope` and yields an enumeration-safe 404.
 *
 * Every mutation still posts to the frozen JSON routes: create →
 * `POST /companies/{company}/vacancies`, edit → `PATCH /vacancies/{vacancy}`
 * (If-Match), submit → `POST /vacancies/{vacancy}/submit-review`, owner close →
 * `POST /vacancies/{vacancy}/close`. Approve / reject / publish / suspend /
 * restore are moderation authority and appear nowhere here.
 *
 * The VERIFIED-company authoring gate is enforced by the backend
 * (`CreateCompanyVacancy` → `VACANCY_COMPANY_NOT_VERIFIED`); this controller
 * only communicates it so the recruiter is not surprised.
 */
final class RecruiterVacancyPageController extends Controller
{
    /** `GET /kelola-lowongan`. */
    public function index(Request $request, ListVacancies $query): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        $company = CompanyScope::queryFor($actor)->orderBy('id')->first();

        $filters = array_intersect_key($request->query(), array_flip(['status', 'q']));
        $page = $query->execute(
            $actor,
            $filters,
            $request->string('sort', 'created_at')->toString(),
            $request->string('direction', 'desc')->toString(),
        );

        return Inertia::render('recruiter/LowonganList', [
            'items' => $page->items(),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'filters' => (object) $filters,
            'company' => $this->companyContext($company, $actor),
        ]);
    }

    /** `GET /kelola-lowongan/baru`. */
    public function create(Request $request, GetPublicReferenceData $referenceData): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        $company = CompanyScope::queryFor($actor)->orderBy('id')->first();

        if ($company === null) {
            return $this->forbidden($request);
        }
        if (Gate::forUser($actor)->denies('create', [Vacancy::class, $company])) {
            return $this->forbidden($request);
        }

        return Inertia::render('recruiter/LowonganForm', [
            'mode' => 'create',
            'vacancy' => null,
            'company' => $this->companyContext($company, $actor),
            'reference_data' => $referenceData->execute(),
            'authorable_types' => VacancyType::companyAuthorable(),
            'editable' => true,
            'can_submit' => false,
            'can_close' => false,
            'moderation_trail' => [],
        ]);
    }

    /** `GET /kelola-lowongan/{vacancy}`. */
    public function show(Request $request, int $vacancy, GetVacancy $query, GetPublicReferenceData $referenceData): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        $model = VacancyScope::findFor($actor, $vacancy) ?? throw new VacancyNotFound();

        $detail = $query->execute($actor, $model, ['requirements', 'screening_questions', 'versions']);
        $status = $model->current_status;
        $editable = $status instanceof VacancyStatus && $status->isCompanyEditable()
            && ! Gate::forUser($actor)->denies('update', $model);

        $company = $model->company_id === null
            ? null
            : CompanyScope::queryFor($actor)->whereKey($model->company_id)->first();

        return Inertia::render('recruiter/LowonganForm', [
            'mode' => 'edit',
            'vacancy' => $detail,
            'company' => $this->companyContext($company, $actor),
            'reference_data' => $referenceData->execute(),
            'authorable_types' => VacancyType::companyAuthorable(),
            'editable' => $editable,
            // Submit / resubmit — owner action, DRAFT or REVISION_REQUIRED only.
            'can_submit' => $status instanceof VacancyStatus
                && $status->isCompanyEditable()
                && ! Gate::forUser($actor)->denies('submitForReview', $model),
            // Owner close — PUBLISHED only (ModerateVacancy::target CLOSE).
            'can_close' => $status === VacancyStatus::Published
                && ! Gate::forUser($actor)->denies('close', $model),
            // Recruiter-safe moderation trail: internal_note is never selected.
            'moderation_trail' => $this->moderationTrail((int) $model->getKey()),
        ]);
    }

    /**
     * Recruiter-visible slice of `vacancy_moderation_reviews` for one
     * already-authorized vacancy. `internal_note` is never read. This mirrors
     * the frozen `GET /vacancies/{vacancy}` contract's "moderation history
     * references, `internal_note` filtered for recruiters" without adding a
     * domain include. Ordered oldest → newest.
     *
     * @return list<array<string, mixed>>
     */
    private function moderationTrail(int $vacancyId): array
    {
        return DB::table('vacancy_moderation_reviews')
            ->where('vacancy_id', $vacancyId)
            ->orderBy('reviewed_at')->orderBy('id')
            ->get(['action', 'from_status', 'to_status', 'reason_category', 'recruiter_visible_note', 'reviewed_at'])
            ->map(static fn ($row): array => [
                'action' => $row->action,
                'from_status' => $row->from_status,
                'to_status' => $row->to_status,
                'reason_category' => $row->reason_category,
                'recruiter_visible_note' => $row->recruiter_visible_note,
                'reviewed_at' => $row->reviewed_at,
            ])
            ->values()->all();
    }

    /** @return array<string, mixed>|null */
    private function companyContext(?object $company, User $actor): ?array
    {
        if ($company === null) {
            return null;
        }

        $verified = $company->verification_status === CompanyStatus::Verified
            || $company->verification_status === CompanyStatus::Verified->value;

        return [
            'id' => (int) $company->id,
            'name' => $company->name,
            'verification_status' => $company->verification_status instanceof CompanyStatus
                ? $company->verification_status->value
                : $company->verification_status,
            'can_author_vacancy' => $verified
                && ! Gate::forUser($actor)->denies('create', [Vacancy::class, $company]),
        ];
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function forbidden(Request $request): Response|JsonResponse|SymfonyResponse
    {
        if ($request->expectsJson()) {
            return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
        }

        return Inertia::render('Error', ['status' => 403])->toResponse($request)->setStatusCode(403);
    }
}
