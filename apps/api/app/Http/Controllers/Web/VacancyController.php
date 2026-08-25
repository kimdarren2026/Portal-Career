<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Company\Support\CompanyScope;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Actions\CreateCompanyVacancy;
use App\Domains\Vacancy\Actions\UpdateVacancy;
use App\Domains\Vacancy\Exceptions\VacancyCompanyNotVerified;
use App\Domains\Vacancy\Exceptions\VacancyNotEditable;
use App\Domains\Vacancy\Exceptions\VacancyNotFound;
use App\Domains\Vacancy\Exceptions\VacancyStaleVersion;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Queries\GetVacancy;
use App\Domains\Vacancy\Queries\ListVacancies;
use App\Domains\Vacancy\Queries\ListVacancyVersions;
use App\Domains\Vacancy\Support\VacancyPresenter;
use App\Domains\Vacancy\Support\VacancyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vacancy\CreateCompanyVacancyRequest;
use App\Http\Requests\Vacancy\UpdateVacancyRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Company vacancy authoring surface.
 *
 * Moderation, submit-review, publish, close, suspend and restore are
 * deliberately absent from this controller: each depends on an unresolved
 * decision and none is implemented, stubbed, or routed.
 */
final class VacancyController extends Controller
{
    public function store(CreateCompanyVacancyRequest $request, int $company, CreateCompanyVacancy $action): JsonResponse
    {
        $actor = $this->actor($request);
        // The company is resolved through COMPANY_SCOPE, never from the payload.
        $target = CompanyScope::findFor($actor, $company) ?? throw new VacancyNotFound();

        if (Gate::forUser($actor)->denies('create', [Vacancy::class, $target])) {
            return $this->forbidden($request);
        }

        try {
            $vacancy = $action->execute($actor, $target, $request->validated());
        } catch (VacancyCompanyNotVerified) {
            return ContractResponse::error($request, 'VACANCY_COMPANY_NOT_VERIFIED', 403, 'Perusahaan harus terverifikasi sebelum membuat lowongan.');
        }

        return ContractResponse::success($request, VacancyPresenter::detail($vacancy, ['requirements', 'screening_questions']), 201)
            ->header('Location', route('vacancies.show', ['vacancy' => $vacancy->getKey()]));
    }

    public function index(Request $request, ListVacancies $query): JsonResponse
    {
        $actor = $this->actor($request);

        foreach (array_keys($request->query()) as $parameter) {
            if (! in_array($parameter, [...ListVacancies::FILTERS, 'sort', 'direction', 'page'], true)) {
                return ContractResponse::error($request, 'VALIDATION_FAILED', 422, 'Filter tidak didukung.', [
                    'fields' => [$parameter => ['Filter tidak didukung.']],
                ]);
            }
        }
        $sort = $request->string('sort', 'created_at')->toString();
        if (! in_array($sort, ListVacancies::SORTABLE, true)) {
            return ContractResponse::error($request, 'VALIDATION_FAILED', 422, 'Sort tidak didukung.', [
                'fields' => ['sort' => ['Sort tidak didukung.']],
            ]);
        }

        $vacancies = $query->execute(
            $actor,
            array_intersect_key($request->query(), array_flip(ListVacancies::FILTERS)),
            $sort,
            $request->string('direction', 'desc')->toString(),
        );

        return ContractResponse::success($request, [
            'items' => $vacancies->items(),
            'pagination' => [
                'page' => $vacancies->currentPage(),
                'per_page' => $vacancies->perPage(),
                'total' => $vacancies->total(),
                'last_page' => $vacancies->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $vacancy, GetVacancy $query): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->scoped($actor, $vacancy);

        $requested = array_filter(array_map('trim', explode(',', $request->string('include')->toString())));
        $include = array_values(array_intersect($requested, GetVacancy::INCLUDES));

        return ContractResponse::success($request, $query->execute($actor, $model, $include));
    }

    public function update(UpdateVacancyRequest $request, int $vacancy, UpdateVacancy $action): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->scoped($actor, $vacancy);

        if (Gate::forUser($actor)->denies('update', $model)) {
            return $this->forbidden($request);
        }

        try {
            $updated = $action->execute($actor, $model, $request->validated(), $this->expectedVersion($request));
        } catch (VacancyNotEditable) {
            return ContractResponse::error($request, 'VACANCY_NOT_EDITABLE', 409, 'Lowongan tidak dapat diubah pada status ini.');
        } catch (VacancyStaleVersion) {
            return ContractResponse::error($request, 'STALE_VERSION', 409, 'Versi lowongan sudah berubah. Muat ulang sebelum menyimpan.');
        }

        return ContractResponse::success($request, VacancyPresenter::detail($updated));
    }

    public function versions(Request $request, int $vacancy, ListVacancyVersions $query): JsonResponse
    {
        $model = $this->scoped($this->actor($request), $vacancy);

        return ContractResponse::success($request, ['items' => $query->execute($model)]);
    }

    /** If-Match carries the version the client last read (PATCH concurrency rule). */
    private function expectedVersion(Request $request): ?int
    {
        $header = trim((string) $request->header('If-Match', ''));
        $header = trim($header, '"');

        return $header === '' || ! ctype_digit($header) ? null : (int) $header;
    }

    private function scoped(User $actor, int $vacancyId): Vacancy
    {
        return VacancyScope::findFor($actor, $vacancyId) ?? throw new VacancyNotFound();
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function forbidden(Request $request): JsonResponse
    {
        return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
    }
}
