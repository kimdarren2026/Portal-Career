<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Queries\ListAuditLogs;
use App\Domains\Audit\Support\AuditLogPresenter;
use App\Domains\Audit\Support\AuditLogScope;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\RoleCatalogue;
use App\Domains\MasterData\Queries\ListMasterDataCollection;
use App\Domains\Notification\Models\SmtpConfiguration;
use App\Domains\Notification\Support\SmtpConfigurationPresenter;
use App\Domains\Vacancy\Enums\TargetAudience;
use App\Domains\Vacancy\Enums\VacancyType;
use App\Domains\Vacancy\Support\VacancyTypeReference;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\ListAuditLogsRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Non-mutating Inertia delivery for the Super Admin control-plane pages
 * (Frontend Vertical Slice v10). Currently the single canonical Super Admin
 * module with a frozen runtime — "Audit Log" (FSD §4.6, §5.13 FR-AUD-001;
 * API_CONTRACT.md `GET /api/v1/audit-logs`, reclassified `INERTIA_WEB` with
 * the rest of the back-office surface, §Surface split).
 *
 * Read-only: `audit_logs` is physically append-only (DB role has SELECT/INSERT
 * only) and the contract defines no create/update/delete for any role,
 * Super Admin included (INV-016). Reads reuse the frozen `AuditLogScope` /
 * `ListAuditLogs` / `AuditLogPresenter` directly — never a loopback HTTP call.
 *
 * Explicit persona gate: `SUPER_ADMIN` or `AUDITOR`, both `READ_ONLY`
 * (AUTHORIZATION_MATRIX.md §4.9). Every other persona — recruiter, candidate,
 * Career Center, Admin Kepegawaian — receives the shared 403. Broad global
 * read authority elsewhere never substitutes for this check.
 */
final class SuperAdminPageController extends Controller
{
    /** `GET /audit-log`. */
    public function auditLogIndex(ListAuditLogsRequest $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! AuditLogScope::isAuditReader($actor)) {
            return $this->forbidden($request);
        }

        $filters = array_intersect_key($request->validated(), array_flip(ListAuditLogs::FILTERS));
        $perPage = $request->integer('per_page') ?: null;

        $page = app(ListAuditLogs::class)->execute(AuditLogScope::queryFor($actor), $filters, $perPage);

        $actorNames = $this->actorNames($page);

        $items = collect($page->items())
            ->map(AuditLogPresenter::summary(...))
            ->map(fn (array $row): array => $row + [
                'actor_name' => $row['actor_user_id'] === null ? null : ($actorNames[$row['actor_user_id']] ?? null),
            ])
            ->values()->all();

        return Inertia::render('super-admin/AuditLog', [
            'items' => $items,
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'filters' => (object) $filters,
            'action_options' => $this->distinctColumn('action'),
            'object_type_options' => $this->distinctColumn('object_type'),
        ]);
    }

    /**
     * `GET /jenis-lowongan` — the Super Admin "Jenis Lowongan" page. Read-only
     * reference over the frozen `VacancyType` enum (PO decision
     * SUPER_ADMIN_VACANCY_TYPE_REFERENCE_MVP — API_CONTRACT.md Part X item 61).
     * No mutation route exists; there is nothing to create, edit or delete.
     */
    public function vacancyTypeIndex(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        return Inertia::render('super-admin/JenisLowongan', [
            'types' => VacancyTypeReference::all(),
        ]);
    }

    /** `GET /konfigurasi-smtp` — the Super Admin "Konfigurasi SMTP" page. */
    public function smtpConfigurationIndex(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        $current = SmtpConfiguration::query()->where('is_active', true)->first()
            ?? SmtpConfiguration::query()->orderByDesc('id')->first();

        return Inertia::render('super-admin/KonfigurasiSmtp', [
            // Safe metadata only — never `encrypted_password`. `secret_configured`
            // is the sole signal that a credential exists (INV-035). `null` when
            // no configuration has been established.
            'configuration' => $current === null ? null : SmtpConfigurationPresenter::safe($current),
        ]);
    }

    /**
     * `GET /pengguna-role` — "Pengguna dan Role" (PARTIAL_FUNCTIONAL).
     *
     * Deterministic today: the frozen role catalogue (FSD §3.1) and the
     * assign / revoke operations (`POST /admin/users/{user}/roles` and its
     * revoke pair, `AUTHORIZATION_MATRIX.md` §4.9). The page carries no user
     * directory: `GET /admin/users` has no frozen field / filter / pagination
     * contract, and suspend / restore / DISABLED session-termination semantics
     * are unresolved — those controls render as explicitly unavailable, not as
     * "Segera hadir".
     */
    public function userRoleIndex(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        return Inertia::render('super-admin/PenggunaRole', [
            'roles' => RoleCatalogue::all(),
        ]);
    }

    /**
     * `GET /master-data` — "Master Data" (READ_ONLY_REFERENCE).
     *
     * The read side of `GET /api/v1/admin/master-data/{collection}` for the
     * six frozen collections. Writes stay DEFERRED (`API_SIZE_REVIEW.md`
     * DF-1) and no mutation route exists.
     */
    public function masterDataIndex(Request $request, ListMasterDataCollection $query): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        $collection = (string) $request->query('collection', ListMasterDataCollection::COLLECTIONS[0]);
        if (! ListMasterDataCollection::isCollection($collection)) {
            $collection = ListMasterDataCollection::COLLECTIONS[0];
        }

        return Inertia::render('super-admin/MasterData', [
            'collections' => ListMasterDataCollection::COLLECTIONS,
            'result' => $query->execute($collection),
        ]);
    }

    /**
     * `GET /unit-organisasi` — "Unit Organisasi" (READ_ONLY_REFERENCE). A
     * focused view over the `organizational-units` master-data collection
     * (`parent_unit_id` hierarchy). No write operation.
     */
    public function organizationalUnitIndex(Request $request, ListMasterDataCollection $query): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        return Inertia::render('super-admin/UnitOrganisasi', [
            'result' => $query->execute('organizational-units'),
        ]);
    }

    /**
     * `GET /program-studi` — "Program Studi" (READ_ONLY_REFERENCE). A focused
     * view over the `study-programs` master-data collection. No write
     * operation.
     */
    public function studyProgramIndex(Request $request, ListMasterDataCollection $query): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        return Inertia::render('super-admin/ProgramStudi', [
            'result' => $query->execute('study-programs'),
        ]);
    }

    /**
     * `GET /template-workflow` — "Template Workflow" (READ_ONLY_REFERENCE).
     *
     * No reusable workflow-template entity exists (no schema, no frozen
     * contract). The page states the current supported model truthfully:
     * recruitment stages are authored per vacancy. No template CRUD.
     */
    public function templateWorkflowIndex(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        return Inertia::render('super-admin/TemplateWorkflow', [
            'global_templates' => [],
        ]);
    }

    /**
     * `GET /template-notifikasi` — "Template Notifikasi" (READ_ONLY_REFERENCE).
     *
     * No notification-template entity and no frozen notification-type
     * vocabulary exist (`API_CONTRACT.md` Part X item 59). The only
     * deterministic source is the frozen FR-NOTIF-002 trigger → recipient
     * table, which is rendered verbatim. No template editing.
     */
    public function templateNotifikasiIndex(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        // Verbatim from FSD v1.1 FR-NOTIF-002 ("Trigger Email Utama").
        $triggers = [
            ['trigger' => 'Verifikasi email akun', 'recipient' => 'Kandidat / Recruiter'],
            ['trigger' => 'Company profile submitted', 'recipient' => 'Recruiter / Career Center sesuai preferensi'],
            ['trigger' => 'Company perlu perbaikan / verified / rejected / suspended', 'recipient' => 'Recruiter terkait'],
            ['trigger' => 'Vacancy submitted / revision / approved / rejected / published / closed', 'recipient' => 'Recruiter terkait'],
            ['trigger' => 'Application berhasil', 'recipient' => 'Kandidat dan owner lowongan sesuai preferensi'],
            ['trigger' => 'Status seleksi berubah', 'recipient' => 'Kandidat sesuai visibility'],
            ['trigger' => 'Jadwal dibuat / diubah / dibatalkan', 'recipient' => 'Kandidat dan petugas terkait'],
            ['trigger' => 'Offering diterbitkan', 'recipient' => 'Kandidat'],
            ['trigger' => 'Offering accepted / rejected', 'recipient' => 'Owner lowongan'],
            ['trigger' => 'Outcome belum lengkap', 'recipient' => 'Recruiter'],
            ['trigger' => 'Partnership akan berakhir', 'recipient' => 'Career Center / PIC'],
        ];

        return Inertia::render('super-admin/TemplateNotifikasi', [
            'triggers' => $triggers,
        ]);
    }

    /**
     * `GET /integrasi` — "Integrasi" (READ_ONLY_REFERENCE).
     *
     * Truthful capability/status only — no integration record, connector, or
     * credential form. Alumni-verification source is an OPEN decision
     * (`API_CONTRACT.md` Part X item 1 / backlog D-3); no two-way ATS
     * integration is in the frozen MVP.
     */
    public function integrationIndex(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        $externalApplyRuntime = Route::has('external-apply-events.confirm')
            || Route::has('vacancies.external-apply.start');

        return Inertia::render('super-admin/Integrasi', [
            'capabilities' => [
                [
                    'key' => 'alumni_verification_source',
                    'label' => 'Sumber verifikasi alumni',
                    'status' => 'PENDING_DECISION',
                    'detail' => 'Keputusan bisnis belum diambil (API_CONTRACT Part X item 1 / D-3). Belum ada klien integrasi, sinkronisasi, callback, atau SSO.',
                ],
                [
                    'key' => 'two_way_ats',
                    'label' => 'Integrasi ATS dua arah',
                    'status' => 'NOT_IN_MVP',
                    'detail' => 'Tidak termasuk dalam MVP beku. Tidak ada sinkronisasi, polling, atau webhook yang dispesifikasikan.',
                ],
                [
                    'key' => 'external_apply_events',
                    'label' => 'Event lamaran eksternal (inbound)',
                    'status' => $externalApplyRuntime ? 'AVAILABLE' : 'NOT_CONFIGURED',
                    'detail' => 'Konfirmasi lamaran eksternal berbasis event; hanya bentuk inbound, tidak ada sinkronisasi keluar.',
                ],
            ],
        ]);
    }

    /**
     * `GET /retensi-data` — "Retensi Data" (READ_ONLY_REFERENCE).
     *
     * Renders the effective frozen policy (FR-AUD-002). There is no
     * operational deletion / anonymization workflow, and no retention period
     * is invented. No purge/anonymize control.
     */
    public function dataRetentionIndex(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        return Inertia::render('super-admin/RetensiData', [
            'policy' => [
                'recruitment_data_retention' => 'INDEFINITE',
                'user_hard_delete' => false,
                'deletion_channel' => 'AUTHORIZED_INSTITUTIONAL_PROCESS_ONLY',
                'operational_workflow_configured' => false,
                'statements' => [
                    'Data rekrutmen dipertahankan tanpa batas waktu sebagai kebijakan bisnis default.',
                    'Recruiter/kandidat biasa tidak memiliki penghapusan permanen (hard delete) atas riwayat rekrutmen historis.',
                    'Penghapusan/anonimisasi hanya melalui proses retensi yang diaudit dan diperintahkan oleh kebijakan institusi yang berwenang.',
                    'Alur operasional penghapusan/anonimisasi belum dikonfigurasi pada sistem ini.',
                ],
            ],
        ]);
    }

    /**
     * `GET /pengaturan-sistem` — "Pengaturan Sistem" (READ_ONLY_REFERENCE).
     *
     * A safe, non-secret configuration overview. No `system_settings` table,
     * no generic key/value store. Never exposes `APP_KEY`, DB password, DSN,
     * SMTP secret, object-storage keys, or tokens — only capability names and
     * configured/not-configured status.
     */
    public function systemSettingsIndex(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        $smtpConfigured = SmtpConfiguration::query()->where('is_active', true)->exists();
        $defaultDisk = (string) config('filesystems.default');
        $diskDriver = (string) config("filesystems.disks.{$defaultDisk}.driver", 'tidak tersedia');

        $activeModules = [
            'Jenis Lowongan', 'Konfigurasi SMTP', 'Audit Log',
            'Master Data (baca)', 'Unit Organisasi (baca)', 'Program Studi (baca)',
            'Pengguna dan Role (parsial)',
        ];

        return Inertia::render('super-admin/PengaturanSistem', [
            'overview' => [
                'application' => [
                    'name' => (string) config('app.name'),
                    'environment' => (string) app()->environment(),
                    'locale' => (string) config('app.locale'),
                    'timezone' => (string) config('app.timezone'),
                ],
                'capabilities' => [
                    ['label' => 'Database', 'value' => (string) config('database.default')],
                    ['label' => 'Cache store', 'value' => (string) config('cache.default')],
                    ['label' => 'Queue connection', 'value' => (string) config('queue.default')],
                    ['label' => 'Session driver', 'value' => (string) config('session.driver')],
                    ['label' => 'Penyimpanan berkas (driver)', 'value' => $diskDriver],
                    ['label' => 'Konfigurasi SMTP aktif', 'value' => $smtpConfigured ? 'Terkonfigurasi' : 'Tidak dikonfigurasi / tidak tersedia'],
                ],
                'super_admin_modules_active' => $activeModules,
                'frozen_vocabularies' => [
                    ['label' => 'Jenis lowongan (VacancyType)', 'count' => count(VacancyType::cases())],
                    ['label' => 'Target audiens (TargetAudience)', 'count' => count(TargetAudience::cases())],
                    ['label' => 'Katalog role (RoleCode)', 'count' => count(RoleCode::cases())],
                ],
            ],
        ]);
    }

    /**
     * Batched display-name lookup for the actors on the current page only —
     * one `whereIn`, never an N+1 relation load. Name only; email and every
     * other identity field stay out of the audit read model.
     *
     * @return array<int, string>
     */
    private function actorNames(LengthAwarePaginator $page): array
    {
        $ids = collect($page->items())
            ->pluck('actor_user_id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        return DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')
            ->map(static fn ($name): string => (string) $name)->all();
    }

    /**
     * The distinct values actually present for a low-cardinality column, for
     * the filter dropdowns. This reflects real data — it invents no
     * vocabulary — and the audit action/object-type sets are small and bounded.
     *
     * @return list<string>
     */
    private function distinctColumn(string $column): array
    {
        return AuditLog::query()->select($column)->distinct()->orderBy($column)
            ->pluck($column)->filter()->values()->all();
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
}
