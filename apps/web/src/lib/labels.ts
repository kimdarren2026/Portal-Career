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

export function formatDateTime(value: string | null | undefined, timezone?: string | null): string {
    if (!value) return '-'
    try {
        return new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: timezone || undefined,
        }).format(new Date(value))
    } catch {
        return new Date(value).toLocaleString('id-ID')
    }
}
