<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Actions\SaveScreeningQuestion;
use App\Domains\Vacancy\Exceptions\ScreeningQuestionNotFound;
use App\Domains\Vacancy\Exceptions\VacancyNotEditable;
use App\Domains\Vacancy\Exceptions\VacancyNotFound;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Support\VacancyPresenter;
use App\Domains\Vacancy\Support\VacancyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vacancy\SaveScreeningQuestionRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Vacancy screening questions (FR-VAC-003, FR-HR-002).
 *
 * GET, POST and PATCH only. There is deliberately no DELETE: a question that
 * already has answers can never be removed, and `PATCH active=false` covers
 * every case including a never-answered question (`API_SIZE_REVIEW.md` Q-1).
 */
final class VacancyScreeningQuestionController extends Controller
{
    public function index(Request $request, int $vacancy): JsonResponse
    {
        $model = $this->scoped($this->actor($request), $vacancy);

        return ContractResponse::success($request, [
            'items' => $model->screeningQuestions()->orderBy('sort_order')->orderBy('id')
                ->get()->map(VacancyPresenter::screeningQuestion(...))->values()->all(),
        ]);
    }

    public function store(SaveScreeningQuestionRequest $request, int $vacancy, SaveScreeningQuestion $action): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->scoped($actor, $vacancy);
        if (Gate::forUser($actor)->denies('manageScreeningQuestions', $model)) {
            return $this->forbidden($request);
        }

        try {
            $question = $action->create($actor, $model, $request->validated());
        } catch (VacancyNotEditable) {
            return $this->notEditable($request);
        }

        return ContractResponse::success($request, VacancyPresenter::screeningQuestion($question), 201);
    }

    public function update(SaveScreeningQuestionRequest $request, int $vacancy, int $question, SaveScreeningQuestion $action): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->scoped($actor, $vacancy);
        if (Gate::forUser($actor)->denies('manageScreeningQuestions', $model)) {
            return $this->forbidden($request);
        }

        // A question is reachable only through its own vacancy (INV-019).
        $target = $model->screeningQuestions()->whereKey($question)->first()
            ?? throw new ScreeningQuestionNotFound();

        try {
            $updated = $action->update($actor, $model, $target, $request->validated());
        } catch (VacancyNotEditable) {
            return $this->notEditable($request);
        }

        return ContractResponse::success($request, VacancyPresenter::screeningQuestion($updated));
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

    private function notEditable(Request $request): JsonResponse
    {
        return ContractResponse::error($request, 'VACANCY_NOT_EDITABLE', 409, 'Lowongan tidak dapat diubah pada status ini.');
    }
}
