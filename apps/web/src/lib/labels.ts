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

const MONTHS_ID = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']

/**
 * `selection_schedules.starts_at`/`ends_at` pair with a separate `timezone`
 * column: the stored value's clock digits ARE the wall-clock time in that
 * named zone — it is not a true UTC instant to re-convert. Passing
 * `timezone` here therefore reads the ISO string's literal date/time digits
 * directly and appends the zone name, never constructing a `Date` (which
 * would treat the trailing offset as real and re-convert into the viewer's
 * own browser zone — a double conversion). A genuine instant (no paired
 * `timezone`, e.g. `first_applied_at`, audit `occurred_at`) still converts
 * normally into the viewer's local zone via `Intl.DateTimeFormat`.
 */
export function formatDateTime(value: string | null | undefined, timezone?: string | null): string {
    if (!value) return '-'

    if (timezone) {
        const match = value.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/)
        if (match) {
            const [, year, month, day, hour, minute] = match
            return `${parseInt(day, 10)} ${MONTHS_ID[parseInt(month, 10) - 1]} ${year}, ${hour}.${minute} (${timezone})`
        }
    }

    try {
        return new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value))
    } catch {
        return new Date(value).toLocaleString('id-ID')
    }
}
