<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/** Builds the deliberately small, authoritative `/me` session context. */
final class GetCurrentUserContext
{
    /** @return array<string, mixed> */
    public function execute(User $user): array
    {
        $roles = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $user->getKey())
            ->whereNull('user_roles.revoked_at')
            ->orderBy('roles.code')
            ->pluck('roles.code')->all();

        $profile = DB::table('candidate_profiles')->where('user_id', $user->getKey())->first();
        $candidateProfile = null;
        if ($profile !== null) {
            $candidateProfile = [
                'id' => (int) $profile->id,
                'current_candidate_type' => $profile->current_candidate_type,
                'profile_completed_at' => $profile->profile_completed_at,
                'verified_eligibility' => DB::table('candidate_verifications')
                    ->where('candidate_profile_id', $profile->id)
                    ->where('status', 'VERIFIED')
                    ->orderBy('verification_type')
                    ->pluck('verification_type')->all(),
            ];
        }

        $memberships = DB::table('company_members')
            ->join('companies', 'companies.id', '=', 'company_members.company_id')
            ->where('company_members.user_id', $user->getKey())
            ->whereNull('company_members.revoked_at')
            ->where('company_members.status', 'ACTIVE')
            ->orderBy('companies.name')
            ->get([
                'companies.id', 'companies.name', 'company_members.company_role', 'companies.verification_status',
            ])->map(fn (object $membership): array => [
                'id' => (int) $membership->id,
                'name' => $membership->name,
                'company_role' => $membership->company_role,
                'verification_status' => $membership->verification_status,
            ])->all();

        return [
            'user' => [
                'id' => (int) $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'status' => $user->status->value,
            ],
            'roles' => $roles,
            'candidate_profile' => $candidateProfile,
            'company_memberships' => $memberships,
            'unread_notification_count' => DB::table('notifications')
                ->where('user_id', $user->getKey())->whereNull('read_at')->count(),
            'portal_contexts' => $this->portalContexts($roles),
        ];
    }

    /** @param list<string> $roles @return list<string> */
    private function portalContexts(array $roles): array
    {
        $contexts = [];
        foreach ($roles as $role) {
            $contexts[] = match ($role) {
                'CANDIDATE_EXTERNAL', 'CANDIDATE_STUDENT_FINAL_YEAR', 'CANDIDATE_ALUMNI' => 'CANDIDATE',
                'COMPANY_ADMIN', 'COMPANY_RECRUITER' => 'RECRUITER',
                'CAREER_CENTER_STAFF', 'CAREER_CENTER_MANAGER' => 'CAREER_CENTER',
                'HR_ADMIN' => 'HR',
                'SELECTOR' => 'SELECTOR',
                'AUDITOR' => 'AUDITOR',
                'SUPER_ADMIN' => 'SUPER_ADMIN',
                default => null,
            };
        }

        return array_values(array_unique(array_filter($contexts)));
    }
}
