<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\Inertia;

/** Non-mutating Inertia delivery routes; tokens are never consumed here. */
final class AuthPageController extends Controller
{
    public function login(): Response { return Inertia::render('auth/Login'); }
    public function register(Request $request): Response { return Inertia::render('auth/Register', ['accountType' => $request->query('type', 'candidate')]); }
    public function forgotPassword(): Response { return Inertia::render('auth/ForgotPassword'); }
    public function resetPassword(string $token): Response { return Inertia::render('auth/ResetPassword', ['token' => $token]); }
    public function verifyEmail(string $token): Response { return Inertia::render('auth/VerifyEmail', ['token' => $token]); }
}
