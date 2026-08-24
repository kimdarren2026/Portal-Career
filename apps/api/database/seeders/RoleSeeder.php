<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The immutable Phase-1 authorization catalog from FSD §3.1. PUBLIC is not a
 * stored role: an unauthenticated visitor has no user_roles assignment.
 */
final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $roles = [
            ['code' => 'CANDIDATE_EXTERNAL', 'name' => 'Kandidat eksternal'],
            ['code' => 'CANDIDATE_STUDENT_FINAL_YEAR', 'name' => 'Mahasiswa tingkat akhir'],
            ['code' => 'CANDIDATE_ALUMNI', 'name' => 'Alumni'],
            ['code' => 'COMPANY_ADMIN', 'name' => 'Admin perusahaan'],
            ['code' => 'COMPANY_RECRUITER', 'name' => 'Recruiter perusahaan'],
            ['code' => 'CAREER_CENTER_STAFF', 'name' => 'Staf Career Center'],
            ['code' => 'CAREER_CENTER_MANAGER', 'name' => 'Kepala Career Center'],
            ['code' => 'HR_ADMIN', 'name' => 'Admin Kepegawaian/HR-SDM'],
            ['code' => 'SELECTOR', 'name' => 'Tim seleksi'],
            ['code' => 'AUDITOR', 'name' => 'Akses baca laporan/audit'],
            ['code' => 'SUPER_ADMIN', 'name' => 'Administrator sistem'],
        ];

        DB::table('roles')->upsert(
            array_map(
                fn (array $role): array => $role + [
                    'description' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $roles,
            ),
            ['code'],
            ['name', 'description', 'updated_at'],
        );
    }
}
