<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\Identity\Actions\AuthenticateUser;
use App\Domains\Identity\Actions\ChangeOwnPassword;
use App\Domains\Identity\Actions\GetCurrentUserContext;
use App\Domains\Identity\Actions\LogoutUser;
use App\Domains\Identity\Actions\RegisterCandidate;
use App\Domains\Identity\Actions\RegisterRecruiter;
use App\Domains\Identity\Actions\RequestPasswordReset;
use App\Domains\Identity\Actions\ResendEmailVerification;
use App\Domains\Identity\Actions\ResetPasswordWithToken;
use App\Domains\Identity\Actions\VerifyEmailToken;
use App\Domains\Identity\Data\AuthenticationOutcome;
use App\Domains\Identity\Exceptions\CurrentPasswordInvalidException;
use App\Domains\Identity\Exceptions\InvalidTokenException;
use App\Domains\Identity\Exceptions\PasswordPolicyException;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Identity\Support\AuthAbuseControls;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\EmailRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCandidateRequest;
use App\Http\Requests\Auth\RegisterRecruiterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** HTTP-only adapter for the ten frozen auth/session operations. */
final class AuthenticationController extends Controller
{
    public function registerCandidate(RegisterCandidateRequest $request, RegisterCandidate $action): JsonResponse
    {
        $data = $request->validated();
        $action->execute($data['name'], $data['email'], $data['password'], $data['candidate_type']);

        return ContractResponse::success($request, ['status' => 'VERIFICATION_EMAIL_QUEUED'], 202, ['EMAIL_DELIVERY_PENDING']);
    }

    public function registerRecruiter(RegisterRecruiterRequest $request, RegisterRecruiter $action): JsonResponse
    {
        $data = $request->validated();
        $action->execute($data['name'], $data['email'], $data['password']);

        return ContractResponse::success($request, ['status' => 'VERIFICATION_EMAIL_QUEUED'], 202, ['EMAIL_DELIVERY_PENDING']);
    }

    public function verifyEmail(VerifyEmailRequest $request, VerifyEmailToken $action): JsonResponse
    {
        try {
            $user = $action->execute($request->string('token')->toString());
        } catch (InvalidTokenException $exception) {
            return ContractResponse::error($request, $exception->errorCode, 422, $exception->getMessage());
        }

        return ContractResponse::success($request, [
            'email_verified' => true,
            'status' => $user->status->value,
        ]);
    }

    public function resendVerification(EmailRequest $request, ResendEmailVerification $action): JsonResponse
    {
        $action->execute($request->string('email')->toString());

        return ContractResponse::success($request, ['status' => 'VERIFICATION_EMAIL_QUEUED'], 202);
    }

    public function login(
        LoginRequest $request,
        AuthenticateUser $action,
        AuthAbuseControls $abuse,
        GetCurrentUserContext $context,
        AuditWriter $audit,
    ): JsonResponse {
        $email = $request->string('email')->toString();
        $result = $action->execute($email, $request->string('password')->toString());

        if ($result['outcome'] === AuthenticationOutcome::InvalidCredentials) {
            $lockRetry = $abuse->recordCredentialFailure($email);
            $audit->record('login_failure', null, 'identity', null);
            if ($lockRetry > 0) {
                $audit->record('login_lock', null, 'identity', null);

                return ContractResponse::error($request, 'AUTH_ACCOUNT_LOCKED', 429, 'Terlalu banyak percobaan masuk. Silakan coba kembali nanti.', retryAfter: $lockRetry);
            }

            return ContractResponse::error($request, 'AUTH_INVALID_CREDENTIALS', 422, 'Email atau password tidak valid.');
        }

        if ($result['outcome'] === AuthenticationOutcome::AccountSuspended) {
            return ContractResponse::error($request, 'AUTH_ACCOUNT_SUSPENDED', 403, 'Akun ini sedang ditangguhkan.');
        }
        if ($result['outcome'] === AuthenticationOutcome::AccountDisabled) {
            return ContractResponse::error($request, 'AUTH_ACCOUNT_DISABLED', 403, 'Akun ini telah dinonaktifkan.');
        }

        /** @var User $user */
        $user = $result['user'];
        $abuse->clearCredentialFailures($email);
        $audit->record('login_success', $user, 'user', (int) $user->getKey());
        $sessionContext = $context->execute($user);

        return ContractResponse::success($request, [
            'user' => $sessionContext['user'],
            'roles' => $sessionContext['roles'],
        ]);
    }

    public function logout(Request $request, LogoutUser $action, AuditWriter $audit): Response
    {
        /** @var User $user */
        $user = $request->user();
        $audit->record('logout', $user, 'user', (int) $user->getKey());
        $action->execute();

        return response()->noContent();
    }

    public function forgotPassword(EmailRequest $request, RequestPasswordReset $action): JsonResponse
    {
        $action->execute($request->string('email')->toString());

        return ContractResponse::success($request, ['status' => 'RESET_EMAIL_QUEUED'], 202);
    }

    public function resetPassword(ResetPasswordRequest $request, ResetPasswordWithToken $action): JsonResponse
    {
        try {
            $action->execute($request->string('token')->toString(), $request->string('password')->toString());
        } catch (InvalidTokenException $exception) {
            return ContractResponse::error($request, $exception->errorCode, 422, $exception->getMessage());
        } catch (PasswordPolicyException $exception) {
            return ContractResponse::error($request, 'AUTH_PASSWORD_POLICY', 422, $exception->getMessage());
        }

        return ContractResponse::success($request, ['password_reset' => true]);
    }

    public function me(Request $request, GetCurrentUserContext $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ContractResponse::success($request, $action->execute($user));
    }

    public function changePassword(ChangePasswordRequest $request, ChangeOwnPassword $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        try {
            $changed = $action->execute(
                $user,
                $request->string('current_password')->toString(),
                $request->string('password')->toString(),
            );
        } catch (CurrentPasswordInvalidException $exception) {
            return ContractResponse::error($request, 'AUTH_CURRENT_PASSWORD_INVALID', 422, $exception->getMessage());
        } catch (PasswordPolicyException $exception) {
            return ContractResponse::error($request, 'AUTH_PASSWORD_POLICY', 422, $exception->getMessage());
        }

        return ContractResponse::success($request, [
            'password_changed_at' => $changed->passwordCredential?->password_changed_at?->toIso8601String(),
        ]);
    }
}
