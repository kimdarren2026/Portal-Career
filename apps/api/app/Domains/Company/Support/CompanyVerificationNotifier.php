<?php

declare(strict_types=1);

namespace App\Domains\Company\Support;

use App\Domains\Company\Models\Company;
use App\Domains\Notification\Support\OutboxWriter;
use Illuminate\Support\Facades\DB;

/** Queues notification work inside the caller's transaction; no SMTP is performed here. */
final class CompanyVerificationNotifier
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    public function queue(Company $company, string $event): void
    {
        $recipients = DB::table('company_members')->join('users', 'users.id', '=', 'company_members.user_id')
            ->where('company_members.company_id', $company->getKey())
            ->where('company_members.status', 'ACTIVE')->whereNull('company_members.revoked_at')
            ->pluck('users.email', 'users.id');
        $careerCenter = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->join('users', 'users.id', '=', 'user_roles.user_id')
            ->whereIn('roles.code', ['CAREER_CENTER_STAFF', 'CAREER_CENTER_MANAGER'])->whereNull('user_roles.revoked_at')->pluck('users.email', 'users.id');
        $all = $recipients->union($careerCenter);
        foreach ($all as $email) {
            $this->outbox->queue((string) $email, 'company.verification.'.$event, ['company_id' => (int) $company->getKey(), 'event' => $event], 'company', (int) $company->getKey());
        }
        foreach ($recipients as $userId => $_email) {
            DB::table('notifications')->insert([
                'user_id' => $userId, 'type' => 'COMPANY_VERIFICATION', 'title' => $event,
                'body_reference' => $event, 'related_object_type' => 'company', 'related_object_id' => $company->getKey(), 'created_at' => now(),
            ]);
        }
    }
}
