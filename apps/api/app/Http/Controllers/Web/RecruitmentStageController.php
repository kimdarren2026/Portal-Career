<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Actions\ReorderRecruitmentStages;
use App\Domains\Vacancy\Actions\SaveRecruitmentStage;
use App\Domains\Vacancy\Exceptions\RecruitmentStageNotFound;
use App\Domains\Vacancy\Exceptions\RecruitmentStageNotInVacancy;
use App\Domains\Vacancy\Exceptions\VacancyNotFound;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Support\VacancyPresenter;
use App\Domains\Vacancy\Support\VacancyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vacancy\ReorderRecruitmentStagesRequest;
use App\Http\Requests\Vacancy\SaveRecruitmentStageRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Recruitment Stage Authoring Foundation v1 (RS-2, RS-6 — approved and
 * CLOSED). `COMPANY` vacancies only — `CAMPUS_SCOPE` is not activated, no
 * Campus vacancy runtime exists.
 *
 * GET, POST, PATCH and the whole-set reorder only. There is deliberately no
 * DELETE: a stage referenced anywhere is DB-protected by `ON DELETE
 * RESTRICT`, and `PATCH active=false` covers deactivation for every case
 * (mirrors `VacancyScreeningQuestionController`, `API_SIZE_REVIEW.md` Q-1/Q-4).
 *
 * RS-2: no `vacancy.current_status` or `company.verification_status` gate —
 * only `manageStages` (`VacancyPolicy`) governs every action here.
 */
final class RecruitmentStageController extends Controller
{
    public function index(Request $request, int $vacancy): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->scoped($actor, $vacancy);
        if (Gate::forUser($actor)->denies('manageStages', $model)) {
            return $this->forbidden($request);
        }

        return ContractResponse::success($request, [
            'items' => $model->stages()->orderBy('sort_order')->orderBy('id')
                ->get()->map(VacancyPresenter::stage(...))->values()->all(),
        ]);
    }

    public function store(SaveRecruitmentStageRequest $request, int $vacancy, SaveRecruitmentStage $action): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->scoped($actor, $vacancy);
        if (Gate::forUser($actor)->denies('manageStages', $model)) {
            return $this->forbidden($request);
        }

        $stage = $action->create($actor, $model, $request->validated());

        return ContractResponse::success($request, VacancyPresenter::stage($stage), 201);
    }

    public function update(SaveRecruitmentStageRequest $request, int $vacancy, int $stage, SaveRecruitmentStage $action): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->scoped($actor, $vacancy);
        if (Gate::forUser($actor)->denies('manageStages', $model)) {
            return $this->forbidden($request);
        }

        // A stage is reachable only through its own vacancy (INV-019).
        $target = $model->stages()->whereKey($stage)->first()
            ?? throw new RecruitmentStageNotFound();

        $updated = $action->update($actor, $model, $target, $request->validated());

        return ContractResponse::success($request, VacancyPresenter::stage($updated));
    }

    public function reorder(ReorderRecruitmentStagesRequest $request, int $vacancy, ReorderRecruitmentStages $action): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->scoped($actor, $vacancy);
        if (Gate::forUser($actor)->denies('manageStages', $model)) {
            return $this->forbidden($request);
        }

        $stageIds = array_map('intval', $request->validated('stage_ids'));

        try {
            $action->execute($actor, $model, $stageIds);
        } catch (RecruitmentStageNotInVacancy) {
            return ContractResponse::error($request, 'STAGE_NOT_IN_VACANCY', 422, 'Susunan tahap tidak sesuai dengan tahap lowongan ini.');
        }

        return ContractResponse::success($request, [
            'items' => $model->stages()->orderBy('sort_order')->orderBy('id')
                ->get()->map(VacancyPresenter::stage(...))->values()->all(),
        ]);
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
