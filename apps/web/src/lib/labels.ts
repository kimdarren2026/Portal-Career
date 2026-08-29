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
