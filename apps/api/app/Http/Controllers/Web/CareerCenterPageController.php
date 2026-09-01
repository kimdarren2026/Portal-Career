<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Support\CompanyModerationPresenter;
use App\Domains\Company\Support\CompanyScope;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Exceptions\VacancyNotFound;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Queries\GetVacancy;
use App\Domains\Vacancy\Queries\ListVacancies;
use App\Domains\Vacancy\Support\VacancyScope;
use App\Http\Controllers\Controller;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Non-mutating Inertia delivery for the Career Center "Verifikasi Perusahaan"
 * and "Moderasi Lowongan" pages (Frontend Vertical Slice v4).
 *
 * Reads reuse the frozen `CompanyScope` / `VacancyScope` / `ListVacancies` /
 * `GetVacancy` classes directly — Career Center is a global reader in both
 * scopes, so no authorization is re-implemented here. Every mutation still
 * posts to the frozen JSON routes: company review →
 * `POST /companies/{company}/{verify|request-revision|reject|suspend|restore}`
 * (`CompanyController@review` → `ReviewCompanyVerification`), vacancy
 * moderation → `POST /vacancies/{vacancy}/{approve|request-revision|reject|
 * suspend|restore|close}` (`VacancyLifecycleController` → `ModerateVacancy`).
 *
 * These pages are Career-Center-persona only (staff or manager). Super Admin's
 * backend moderation authority is unchanged but is not surfaced here — a
 * separate persona would get its own pages. Recruiters and candidates receive
 * the shared 403.
 *
 * `eligible_actions` is a display hint derived from the frozen transition
 * graphs; `CompanyPolicy::review` / `VacancyPolicy::moderate` and the Actions
 * remain the sole authority, including the conflict-of-interest rule (an active
 * member of the owning company may never moderate it).
 */
final class CareerCenterPageController extends Controller
{
    private const COMPANY_STATUSES = [
        'DRAFT', 'PENDING_VERIFICATION', 'REVISION_REQUIRED', 'VERIFIED', 'REJECTED', 'SUSPENDED',
    ];

    /** `GET /verifikasi-perusahaan`. */
    public function companyIndex(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $this->isCareerCenter($actor)) {
            return $this->forbidden($request);
        }

        $status = $request->string('status')->toString();
        $status = in_array($status, self::COMPANY_STATUSES, true) ? $status : null;
        $q = trim($request->string('q')->toString());

        $page = CompanyScope::queryFor($actor)
            ->when($status !== null, fn (Builder $b) => $b->where('verification_status', $status))
            ->when($q !== '', fn (Builder $b) => $b->whereRaw('lower(name) like ?', ['%'.mb_strtolower($q).'%']))
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $companies = $page->items();
        $submittedAt = CompanyModerationPresenter::submittedAtMap(
            array_map(static fn ($c): int => (int) $c->getKey(), $companies),
        );

        return Inertia::render('career-center/VerifikasiPerusahaan', [
            'items' => collect($companies)
                ->map(static fn ($c): array => CompanyModerationPresenter::summary($c, $submittedAt[(int) $c->getKey()] ?? null))
                ->values()->all(),
            'pagination' => self::pagination($page),
            'filters' => (object) array_filter(['status' => $status, 'q' => $q === '' ? null : $q]),
        ]);
    }

    /** `GET /verifikasi-perusahaan/{company}`. */
    public function companyShow(Request $request, int $company): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $this->isCareerCenter($actor)) {
            return $this->forbidden($request);
        }

        $model = CompanyScope::findFor($actor, $company);
        if ($model === null) {
            return $this->notFound($request, 'Perusahaan tidak ditemukan.');
        }

        return Inertia::render('career-center/TinjauPerusahaan', [
            'company' => CompanyModerationPresenter::detail($model),
            'eligible_actions' => CompanyModerationPresenter::eligibleActions($model),
        ]);
    }

    /** `GET /moderasi-lowongan`. */
    public function vacancyIndex(Request $request, ListVacancies $query): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $this->isCareerCenter($actor)) {
            return $this->forbidden($request);
        }

        $filters = array_intersect_key($request->query(), array_flip(ListVacancies::FILTERS));
        $page = $query->execute(
            $actor,
            $filters,
            $request->string('sort', 'created_at')->toString(),
            $request->string('direction', 'desc')->toString(),
        );

        $items = $page->items();
        $companyNames = self::companyNames(array_column($items, 'company_id'));
        foreach ($items as &$item) {
            $item['company_name'] = $companyNames[$item['company_id']] ?? null;
        }
        unset($item);

        return Inertia::render('career-center/ModerasiLowongan', [
            'items' => $items,
            'pagination' => self::pagination($page),
            'filters' => (object) $filters,
        ]);
    }

    /** `GET /moderasi-lowongan/{vacancy}`. */
    public function vacancyShow(Request $request, int $vacancy, GetVacancy $query): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $this->isCareerCenter($actor)) {
            return $this->forbidden($request);
        }

        $model = VacancyScope::findFor($actor, $vacancy) ?? throw new VacancyNotFound();
        $detail = $query->execute($actor, $model, ['requirements', 'screening_questions', 'versions']);

        $company = $model->company_id === null ? null : DB::table('companies')
            ->where('id', $model->company_id)->first(['id', 'name', 'verification_status']);

        return Inertia::render('career-center/TinjauLowongan', [
            'vacancy' => $detail,
            'company' => $company === null ? null : [
                'id' => (int) $company->id,
                'name' => $company->name,
                'verification_status' => $company->verification_status,
            ],
            'eligible_actions' => $this->vacancyEligibleActions($model->current_status),
            'approve_effect' => $this->approveEffect($model),
            // Full moderation trail — Career Center is authorised to see internal_note.
            'moderation_trail' => DB::table('vacancy_moderation_reviews')
                ->where('vacancy_id', $model->getKey())
                ->orderBy('reviewed_at')->orderBy('id')
                ->get(['action', 'from_status', 'to_status', 'reason_category', 'recruiter_visible_note', 'internal_note', 'reviewed_at'])
                ->map(static fn ($r): array => (array) $r)->values()->all(),
        ]);
    }

    /** Frozen `ModerateVacancy` transition graph, as eligible route segments. @return list<string> */
    private function vacancyEligibleActions(?VacancyStatus $status): array
    {
        return match ($status) {
            VacancyStatus::PendingReview => ['approve', 'request-revision', 'reject'],
            VacancyStatus::Published => ['suspend', 'close'],
            VacancyStatus::Suspended => ['restore'],
            default => [],
        };
    }

    /**
     * What `approve` would resolve to right now (B-1) — display hint only.
     * 'scheduled' | 'published' | 'blocked_window' | 'blocked_dates' | null.
     */
    private function approveEffect(Vacancy $vacancy): ?string
    {
        if ($vacancy->current_status !== VacancyStatus::PendingReview) {
            return null;
        }
        if ($vacancy->open_at === null || $vacancy->close_at === null) {
            return 'blocked_dates';
        }
        $now = now();
        if ($now >= $vacancy->close_at) {
            return 'blocked_window';
        }

        return $now < $vacancy->open_at ? 'scheduled' : 'published';
    }

    private function isCareerCenter(User $actor): bool
    {
        return $actor->hasActiveRole(RoleCode::CareerCenterStaff)
            || $actor->hasActiveRole(RoleCode::CareerCenterManager);
    }

    /** @return array<int, string> */
    private static function companyNames(array $companyIds): array
    {
        $ids = array_values(array_unique(array_filter($companyIds, static fn ($id): bool => $id !== null)));

        return $ids === [] ? [] : DB::table('companies')->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /** @return array<string, int> */
    private static function pagination(LengthAwarePaginator $page): array
    {
        return [
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
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

    private function notFound(Request $request, string $message): Response|JsonResponse|SymfonyResponse
    {
        if ($request->expectsJson()) {
            return ContractResponse::error($request, 'NOT_FOUND', 404, $message);
        }

        return Inertia::render('Error', ['status' => 404])->toResponse($request)->setStatusCode(404);
    }
}
