<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Actions\CreateCampusVacancy;
use App\Domains\Vacancy\Actions\TransitionCampusVacancy;
use App\Domains\Vacancy\Enums\CampusVacancyAction;
use App\Domains\Vacancy\Exceptions\VacancyDatesRequired;
use App\Domains\Vacancy\Exceptions\VacancyInvalidTransition;
use App\Domains\Vacancy\Exceptions\VacancyModerationNotApplicable;
use App\Domains\Vacancy\Exceptions\VacancyNotFound;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Support\VacancyPresenter;
use App\Domains\Vacancy\Support\VacancyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vacancy\CreateCampusVacancyRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Campus vacancy authoring and lifecycle for Admin Kepegawaian (`HR_ADMIN`) —
 * `POST /hr/vacancies` and the five `POST /hr/vacancies/{vacancy}/{action}`
 * lifecycle routes (FR-HR-001..004, FSD §8.4).
 *
 * Read (`GET /vacancies`, `GET /vacancies/{vacancy}`), edit
 * (`PATCH /vacancies/{vacancy}`), version history and the stage / screening
 * routes are the SHARED vacancy surface — `VacancyScope` already returns
 * campus vacancies for `HR_ADMIN` and `VacancyPolicy` already authorizes the
 * campus branch, so no campus-specific read/edit controller exists.
 *
 * Campus vacancies are never moderated: there is no submit / request-revision
 * / approve / reject here, and `ModerateVacancy` refuses a campus vacancy.
 */
final class HrVacancyController extends Controller
{
    /** `POST /hr/vacancies`. */
    public function store(CreateCampusVacancyRequest $request, CreateCampusVacancy $action): JsonResponse
    {
        $actor = $this->actor($request);
        if (! $this->isCampusAdmin($actor)) {
            return $this->forbidden($request);
        }

        $vacancy = $action->execute($actor, $request->validated());

        return ContractResponse::success($request, VacancyPresenter::detail($vacancy, ['requirements', 'screening_questions']), 201)
            ->header('Location', route('vacancies.show', ['vacancy' => $vacancy->getKey()]));
    }

    public function publish(Request $request, int $vacancy, TransitionCampusVacancy $action): JsonResponse
    {
        return $this->transition($request, $vacancy, $action, CampusVacancyAction::Publish);
    }

    public function schedule(Request $request, int $vacancy, TransitionCampusVacancy $action): JsonResponse
    {
        return $this->transition($request, $vacancy, $action, CampusVacancyAction::Schedule);
    }

    public function close(Request $request, int $vacancy, TransitionCampusVacancy $action): JsonResponse
    {
        return $this->transition($request, $vacancy, $action, CampusVacancyAction::Close);
    }

    public function suspend(Request $request, int $vacancy, TransitionCampusVacancy $action): JsonResponse
    {
        return $this->transition($request, $vacancy, $action, CampusVacancyAction::Suspend);
    }

    public function restore(Request $request, int $vacancy, TransitionCampusVacancy $action): JsonResponse
    {
        return $this->transition($request, $vacancy, $action, CampusVacancyAction::Restore);
    }

    private function transition(Request $request, int $vacancy, TransitionCampusVacancy $action, CampusVacancyAction $which): JsonResponse
    {
        $actor = $this->actor($request);
        if (! $this->isCampusAdmin($actor)) {
            return $this->forbidden($request);
        }

        $model = VacancyScope::findFor($actor, $vacancy) ?? throw new VacancyNotFound();

        try {
            $updated = $action->execute($actor, $model, $which);
        } catch (VacancyInvalidTransition) {
            return ContractResponse::error($request, 'VACANCY_INVALID_TRANSITION', 409, 'Aksi tidak sah dari status lowongan saat ini.');
        } catch (VacancyDatesRequired) {
            return ContractResponse::error($request, 'VACANCY_DATES_REQUIRED', 422, 'Tanggal buka dan tutup wajib diisi sebelum publikasi.');
        } catch (VacancyModerationNotApplicable) {
            return ContractResponse::error($request, 'VACANCY_MODERATION_NOT_APPLICABLE', 409, 'Aksi ini tidak berlaku untuk lowongan ini.');
        }

        return ContractResponse::success($request, VacancyPresenter::detail($updated));
    }

    private function isCampusAdmin(User $actor): bool
    {
        return $actor->hasActiveRole(RoleCode::HrAdmin);
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
