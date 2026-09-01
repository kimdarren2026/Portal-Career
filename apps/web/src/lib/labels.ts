/**
 * Display-only label maps. These never change the stored status vocabulary
 * (`applications.current_status`, `selection_schedules.status`, etc.) — an
 * unmapped value always falls back to the raw stored string, never a
 * fabricated second status.
 */

export const applicationStatusLabel: Record<string, string> = {
    APPLIED: 'Melamar',
    UNDER_REVIEW: 'Sedang Ditinjau',
    SHORTLISTED: 'Masuk Daftar Pendek',
    ASSESSMENT: 'Tahap Asesmen',
    INTERVIEW: 'Tahap Wawancara',
    OFFERED: 'Mendapat Penawaran',
    HIRED: 'Diterima Bekerja',
    REJECTED: 'Tidak Lolos',
    WITHDRAWN: 'Mengundurkan Diri',
    NO_SHOW: 'Tidak Hadir',
}

export const applicationStatusBadgeClass: Record<string, string> = {
    APPLIED: 'bg-slate-100 text-slate-700',
    UNDER_REVIEW: 'bg-amber-100 text-amber-800',
    SHORTLISTED: 'bg-sky-100 text-sky-800',
    ASSESSMENT: 'bg-sky-100 text-sky-800',
    INTERVIEW: 'bg-sky-100 text-sky-800',
    OFFERED: 'bg-indigo-100 text-indigo-800',
    HIRED: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
    WITHDRAWN: 'bg-slate-200 text-slate-700',
    NO_SHOW: 'bg-red-100 text-red-800',
}

/** RA-1 Foundation v1's exact frozen edge set — five edges only. UX guidance only; the Action's transition graph remains authority. */
export const allowedTransitions: Record<string, string[]> = {
    APPLIED: ['UNDER_REVIEW', 'REJECTED'],
    UNDER_REVIEW: ['SHORTLISTED', 'REJECTED'],
    SHORTLISTED: ['REJECTED'],
}

export const scheduleStatusLabel: Record<string, string> = {
    SCHEDULED: 'Terjadwal',
    RESCHEDULED: 'Dijadwalkan Ulang',
    CANCELLED: 'Dibatalkan',
    COMPLETED: 'Selesai',
    NO_SHOW: 'Tidak Hadir',
}

export const scheduleMethodLabel: Record<string, string> = {
    ONLINE: 'Daring (Online)',
    ON_SITE: 'Tatap Muka (On-site)',
}

export const scheduleEventLabel: Record<string, string> = {
    CREATED: 'Jadwal dibuat',
    RESCHEDULED: 'Jadwal diubah',
    CANCELLED: 'Jadwal dibatalkan',
}

export function statusLabel(map: Record<string, string>, value: string | null | undefined): string {
    if (!value) return '-'
    return map[value] ?? value
}

/**
 * FE-3 (approved PO decision, 29 August 2026 — API_CONTRACT.md Part X row 49).
 * Display labels for the fixed `vacancies.vacancy_type` enum. Display only;
 * the stored values and `VacancyType` enum are unchanged.
 */
export const vacancyTypeLabel: Record<string, string> = {
    CAMPUS_EMPLOYMENT: 'Karier di Kampus',
    COMPANY_EMPLOYMENT: 'Pekerjaan di Perusahaan',
    INTERNSHIP: 'Magang',
}

/**
 * FE-9 (approved PO decision, 1 September 2026 — API_CONTRACT.md Part X row 55,
 * completing FE-4 row 50). The recruiter company-vacancy authoring vocabulary
 * for `vacancies.employment_type`: exactly these five codes are offered in the
 * create/edit form and submitted raw. Display/authoring-control labels only —
 * no CHECK, migration, backend `Rule::in`, or enum. An unknown legacy stored
 * value still falls through to its raw string via `statusLabel`, never a
 * manufactured label.
 */
export const employmentTypeLabel: Record<string, string> = {
    FULL_TIME: 'Penuh Waktu',
    PART_TIME: 'Paruh Waktu',
    CONTRACT: 'Kontrak',
    FREELANCE: 'Freelance',
    TEMPORARY: 'Sementara',
}

/** FE-9: the exact ordered option set the authoring form offers. Raw code is submitted. */
export const employmentTypeOptions = ['FULL_TIME', 'PART_TIME', 'CONTRACT', 'FREELANCE', 'TEMPORARY'] as const

/**
 * FE-7 (approved PO decision, 29 August 2026 — API_CONTRACT.md Part X row 53,
 * completing FE-5). The v1 `vacancies.workplace_mode` display vocabulary,
 * closed for exactly these three values. The single authoritative frontend
 * map — used for vacancy badges, metadata and filter option labels alike.
 * Stored/request values are unchanged; an unknown value falls through to its
 * raw string via `statusLabel`, never a manufactured label.
 */
export const workplaceModeLabel: Record<string, string> = {
    ONSITE: 'On-site',
    HYBRID: 'Hybrid',
    REMOTE: 'Remote',
}

/** FE-7: the exact ordered option set the authoring form offers. Raw code is submitted. */
export const workplaceModeOptions = ['ONSITE', 'HYBRID', 'REMOTE'] as const

/**
 * FE-10 (approved PO decision, 1 September 2026 — API_CONTRACT.md Part X row 56).
 * Display labels for the fixed `companies.verification_status` enum
 * (`App\Domains\Company\Enums\CompanyStatus`). Display only; stored values and
 * the enum are unchanged. "Terverifikasi" is a verification state and carries
 * no Mitra Kampus / partnership meaning. An unmapped value falls through to its
 * raw string via `statusLabel`.
 */
export const companyStatusLabel: Record<string, string> = {
    DRAFT: 'Draf',
    PENDING_VERIFICATION: 'Menunggu Verifikasi',
    REVISION_REQUIRED: 'Perlu Perbaikan',
    VERIFIED: 'Terverifikasi',
    REJECTED: 'Ditolak',
    SUSPENDED: 'Ditangguhkan',
}

export const companyStatusBadgeClass: Record<string, string> = {
    DRAFT: 'bg-slate-100 text-slate-700',
    PENDING_VERIFICATION: 'bg-amber-100 text-amber-800',
    REVISION_REQUIRED: 'bg-red-100 text-red-800',
    VERIFIED: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
    SUSPENDED: 'bg-slate-200 text-slate-700',
}

/**
 * FE-11 (approved PO decision, 1 September 2026 — API_CONTRACT.md Part X row 57).
 * Display labels for the fixed `vacancies.current_status` enum
 * (`App\Domains\Vacancy\Enums\VacancyStatus`). Display only; stored values and
 * the enum are unchanged. `SUBMITTED`/`DIAJUKAN` does not exist — submit is an
 * action, never a status. An unmapped value falls through to its raw string.
 */
export const vacancyStatusLabel: Record<string, string> = {
    DRAFT: 'Draf',
    PENDING_REVIEW: 'Menunggu Tinjauan',
    REVISION_REQUIRED: 'Perlu Perbaikan',
    APPROVED: 'Disetujui',
    SCHEDULED: 'Terjadwal',
    PUBLISHED: 'Dipublikasikan',
    REJECTED: 'Ditolak',
    CLOSED: 'Ditutup',
    EXPIRED: 'Kedaluwarsa',
    SUSPENDED: 'Ditangguhkan',
}

export const vacancyStatusBadgeClass: Record<string, string> = {
    DRAFT: 'bg-slate-100 text-slate-700',
    PENDING_REVIEW: 'bg-amber-100 text-amber-800',
    REVISION_REQUIRED: 'bg-red-100 text-red-800',
    APPROVED: 'bg-sky-100 text-sky-800',
    SCHEDULED: 'bg-sky-100 text-sky-800',
    PUBLISHED: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
    CLOSED: 'bg-slate-200 text-slate-700',
    EXPIRED: 'bg-slate-200 text-slate-700',
    SUSPENDED: 'bg-slate-200 text-slate-700',
}

/**
 * FE-12 (approved PO decision, 1 September 2026 — API_CONTRACT.md Part X row 58).
 * Display labels for the fixed `vacancies.target_audience` enum
 * (`App\Domains\Vacancy\Enums\TargetAudience`, INV-006). Display and authoring
 * option labels only; stored values and the enum are unchanged.
 */
export const targetAudienceLabel: Record<string, string> = {
    PUBLIC: 'Publik',
    ALUMNI_ONLY: 'Alumni',
    FINAL_YEAR_AND_ALUMNI: 'Mahasiswa Tingkat Akhir & Alumni',
    INTERNAL: 'Internal',
}

/** INV-006: the four values, offered in this order by the authoring form. Raw code submitted. */
export const targetAudienceOptions = ['PUBLIC', 'ALUMNI_ONLY', 'FINAL_YEAR_AND_ALUMNI', 'INTERNAL'] as const

/**
 * `VacancyType::companyAuthorable()` — the two types a company may author
 * (`POST /companies/{company}/vacancies`; `CAMPUS_EMPLOYMENT` is the separate
 * campus flow, INV-018). Labels reuse the frozen FE-3 `vacancyTypeLabel` map.
 */
export const companyAuthorableVacancyTypes = ['COMPANY_EMPLOYMENT', 'INTERNSHIP'] as const

/** `vacancies.application_method` display labels (FSD §9.1.5). */
export const applicationMethodLabel: Record<string, string> = {
    IN_PORTAL: 'Lamar via Portal',
    EXTERNAL_ATS: 'Lamar via ATS Eksternal',
}

/**
 * Career Center moderation action button labels (Frontend Vertical Slice v4).
 * Keys are the frozen route segments; the value set is the frozen transition
 * graph (`ReviewCompanyVerification` / `ModerateVacancy`) — no action is
 * invented and eligibility is server-derived (`eligible_actions`).
 */
export const moderationActionLabel: Record<string, string> = {
    verify: 'Verifikasi Perusahaan',
    approve: 'Setujui',
    'request-revision': 'Minta Perbaikan',
    reject: 'Tolak',
    suspend: 'Tangguhkan',
    restore: 'Pulihkan',
    close: 'Tutup Lowongan',
}

/** `company_verification_reviews.action` / `vacancy_moderation_reviews.action` history labels. */
export const reviewActionLabel: Record<string, string> = {
    SUBMIT: 'Diajukan',
    VERIFY: 'Diverifikasi',
    APPROVE: 'Disetujui',
    REQUEST_REVISION: 'Perbaikan diminta',
    REJECT: 'Ditolak',
    SUSPEND: 'Ditangguhkan',
    RESTORE: 'Dipulihkan',
    CLOSE: 'Ditutup',
}

/**
 * FE-6 (approved PO decision, 29 August 2026 — API_CONTRACT.md Part X row 52).
 * Recruiter application-history row label. `STAGE_CHANGED` is a stage-only
 * event with no application status — it must never be passed through the
 * application-status label map (which would render "-" for its NULL
 * `to_status`). Every other event type is a status transition whose label is
 * its resulting status. Frontend presentation only; candidate visibility
 * filtering stays with the backend presenter.
 */
export function applicationHistoryLabel(event: { event_type?: string | null; to_status?: string | null }): string {
    if (event.event_type === 'STAGE_CHANGED') return 'Tahap Seleksi Diubah'
    return statusLabel(applicationStatusLabel, event.to_status)
}

/**
 * Candidate application-history row label. A candidate never sees an internal
 * stage name (FR-APP-004): for a candidate-visible `STAGE_CHANGED` the row
 * shows the target stage's `candidate_visible_label` verbatim — the same
 * `stage_label` the backend already puts in the `application.stage_moved`
 * notification — falling back to the bare domain noun "Tahap Seleksi" only
 * when the recruiter left that label unset. INTERNAL stage events never reach
 * the candidate payload (backend `ApplicationPresenter` filter). Every other
 * event type keeps its resulting-status label.
 */
export function candidateHistoryLabel(event: { event_type?: string | null; to_status?: string | null; stage_label?: string | null }): string {
    if (event.event_type === 'STAGE_CHANGED') return event.stage_label || 'Tahap Seleksi'
    return statusLabel(applicationStatusLabel, event.to_status)
}

/**
 * `recruitment_outcomes.outcome` for `source_type = INTERNAL_APPLICATION`
 * (OC-1 — `InternalApplicationOutcome::ALLOWED`). These four codes are the
 * exact terminal `applications.current_status` vocabulary, so their display
 * label is the already-approved `applicationStatusLabel` mapping for the same
 * codes — no separate/invented translation. `statusLabel(applicationStatusLabel, x)`.
 */
export const internalApplicationOutcomeOptions = ['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'] as const

/**
 * `recruitment_outcomes.reported_by_source` — client-supplied, exact schema
 * `CHECK` vocabulary (API_CONTRACT Part …: "no additional actor-to-source
 * mapping is invented or enforced"). No approved Indonesian display label
 * exists, so the raw contract code is shown verbatim; the contract's full set
 * is offered and never silently narrowed.
 */
export const reportedBySourceOptions = ['CANDIDATE', 'COMPANY', 'CAMPUS_STAFF', 'INTEGRATION'] as const

/**
 * The institution's user-facing timezone (DATABASE_SCHEMA.md — "Asia/Jakarta
 * is the expected default for this institution"). Used to render genuine
 * absolute instants that carry no paired timezone of their own, so display
 * never depends on the viewer's machine clock.
 */
const DEFAULT_DISPLAY_TIMEZONE = 'Asia/Jakarta'

/** Offset (zone − UTC) in ms that `timeZone` was at the given instant. */
function zoneOffsetMs(instant: Date, timeZone: string): number {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        hourCycle: 'h23',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).formatToParts(instant)
    const map: Record<string, string> = {}
    for (const part of parts) map[part.type] = part.value
    const asUtc = Date.UTC(
        Number(map.year),
        Number(map.month) - 1,
        Number(map.day),
        Number(map.hour),
        Number(map.minute),
        Number(map.second),
    )
    return asUtc - instant.getTime()
}

/**
 * `selection_schedules.starts_at`/`ends_at` are absolute instants persisted as
 * UTC; `selection_schedules.timezone` is the named zone the appointment was
 * scheduled in (DATABASE_SCHEMA.md — "conversion to a user-facing zone happens
 * at render time"). When a `timezone` is given, the stored instant is parsed
 * and rendered in that zone, then the zone name is shown alongside. A genuine
 * instant with no paired timezone (`first_applied_at`, audit `occurred_at`)
 * renders in the institution default, never the viewer's machine zone.
 */
export function formatDateTime(value: string | null | undefined, timezone?: string | null): string {
    if (!value) return '-'
    const instant = new Date(value)
    if (Number.isNaN(instant.getTime())) return '-'

    const zone = timezone || DEFAULT_DISPLAY_TIMEZONE
    let rendered: string
    try {
        rendered = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: zone }).format(instant)
    } catch {
        rendered = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: DEFAULT_DISPLAY_TIMEZONE }).format(instant)
    }
    return timezone ? `${rendered} (${timezone})` : rendered
}

/**
 * WRITE side of the schedule timezone contract. A `datetime-local` input is a
 * wall-clock time with no zone; interpret it as wall-clock in `timeZone` and
 * return the absolute UTC instant (ISO-8601) the frozen backend contract
 * expects. `10:00` + `Asia/Jakarta` → `2026-…T03:00:00.000Z`.
 */
export function zonedWallTimeToIso(localValue: string, timeZone: string): string {
    const match = localValue.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/)
    if (!match) return localValue
    const [, year, month, day, hour, minute, second] = match
    const naiveUtc = Date.UTC(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute), Number(second ?? 0))
    const zone = safeZone(timeZone)
    const firstPass = zoneOffsetMs(new Date(naiveUtc), zone)
    let instant = naiveUtc - firstPass
    const secondPass = zoneOffsetMs(new Date(instant), zone)
    if (secondPass !== firstPass) instant = naiveUtc - secondPass
    return new Date(instant).toISOString()
}

/**
 * READ side for form prefill: absolute instant → the `datetime-local` string
 * (`YYYY-MM-DDTHH:mm`) that shows the wall-clock time in `timeZone`.
 * `2026-…T03:00:00+00:00` + `Asia/Jakarta` → `2026-…T10:00`.
 */
export function isoToZonedInput(value: string | null | undefined, timeZone: string): string {
    if (!value) return ''
    const instant = new Date(value)
    if (Number.isNaN(instant.getTime())) return ''
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: safeZone(timeZone),
        hourCycle: 'h23',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).formatToParts(instant)
    const map: Record<string, string> = {}
    for (const part of parts) map[part.type] = part.value
    return `${map.year}-${map.month}-${map.day}T${map.hour}:${map.minute}`
}

/** A syntactically valid IANA zone, or the institution default if not resolvable. */
function safeZone(timeZone: string): string {
    try {
        new Intl.DateTimeFormat('en-US', { timeZone })
        return timeZone
    } catch {
        return DEFAULT_DISPLAY_TIMEZONE
    }
}
