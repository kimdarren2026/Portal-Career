<?php

declare(strict_types=1);

namespace App\Domains\Notification\Support;

/**
 * Deterministic renderers for every `template_reference` the production
 * runtime emits into `email_outbox` (PGC-V1 / PD-B).
 *
 * These are minimal, factual, operational messages — never marketing. Each
 * renderer receives the outbox `payload_reference` (which by design carries
 * NO credential or raw token — INV-015 / INV-021) and returns a subject and a
 * plain-text body in the standard layout. An action link is included only
 * where the destination is deterministic from configuration alone (never from
 * a token that is not in the payload).
 *
 * A coverage test (`EmailTemplateCoverageTest`) enumerates `references()` and
 * asserts every entry renders; `OutboxWriter` rejects an unknown reference at
 * write time so a new notifier cannot ship without a template.
 */
final class EmailTemplateCatalog
{
    private const ORG = 'STIKES Advaita Medika Tabanan';
    private const PRODUCT = 'Portal Karir';
    private const FOOTER = 'Email ini dikirim otomatis oleh Portal Karir STIKES Advaita Medika Tabanan.';

    /** @return list<string> every reference this catalog can render */
    public static function references(): array
    {
        return array_keys(self::map());
    }

    public static function has(string $reference): bool
    {
        return array_key_exists($reference, self::map());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{subject: string, body: string}
     */
    public function render(string $reference, array $payload): array
    {
        $renderer = self::map()[$reference] ?? null;
        if ($renderer === null) {
            // Fail closed with a factual generic message rather than throw in
            // the delivery path — the coverage test prevents this in practice.
            return [
                'subject' => self::PRODUCT.' — pemberitahuan',
                'body' => $this->layout('Pemberitahuan', 'Ada pembaruan terkait akun atau aktivitas Anda di Portal Karir.', [], null, self::url('/dashboard')),
            ];
        }

        return $renderer($payload, $this);
    }

    /**
     * @param  array<string, string|int|null>  $meta  label => value lines
     */
    public function layout(string $title, string $description, array $meta = [], ?string $actionLabel = null, ?string $actionUrl = null): string
    {
        $lines = [self::ORG, self::PRODUCT, '', $title, '', $description];

        $metaLines = [];
        foreach ($meta as $label => $value) {
            if ($value !== null && $value !== '') {
                $metaLines[] = $label.': '.$value;
            }
        }
        if ($metaLines !== []) {
            $lines[] = '';
            $lines = array_merge($lines, $metaLines);
        }

        if ($actionUrl !== null) {
            $lines[] = '';
            $lines[] = ($actionLabel ?? 'Buka Portal Karir').': '.$actionUrl;
        }

        $lines[] = '';
        $lines[] = self::FOOTER;

        return implode("\n", $lines);
    }

    private static function url(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }

    /**
     * @return array<string, callable(array<string, mixed>, self): array{subject: string, body: string}>
     */
    private static function map(): array
    {
        $simple = static fn (string $subject, string $title, string $desc, ?string $label = null, ?string $path = null): callable =>
            static fn (array $p, self $c): array => [
                'subject' => $subject,
                'body' => $c->layout($title, $desc, [], $label, $path === null ? null : self::url($path)),
            ];

        return [
            // --- Identity ---
            'identity.email-verification' => $simple(
                'Verifikasi email akun Portal Karir',
                'Verifikasi Email',
                'Permintaan verifikasi email telah dibuat untuk akun Anda. Buka Portal Karir dan gunakan tautan verifikasi yang dikirimkan untuk menyelesaikan pendaftaran.',
                'Buka Portal Karir', '/login',
            ),
            'identity.account-already-registered' => $simple(
                'Pendaftaran akun Portal Karir',
                'Akun Sudah Terdaftar',
                'Alamat email ini sudah terdaftar di Portal Karir. Jika ini Anda, silakan masuk. Jika Anda tidak melakukan pendaftaran, abaikan email ini.',
                'Masuk', '/login',
            ),
            'identity.password-reset' => $simple(
                'Permintaan atur ulang kata sandi',
                'Atur Ulang Kata Sandi',
                'Permintaan atur ulang kata sandi telah dibuat untuk akun Anda. Buka Portal Karir dan gunakan tautan atur ulang yang dikirimkan. Jika Anda tidak meminta ini, abaikan email ini.',
                'Buka Portal Karir', '/login',
            ),
            'identity.password-reset-completed' => $simple(
                'Kata sandi berhasil diatur ulang',
                'Kata Sandi Diatur Ulang',
                'Kata sandi akun Anda berhasil diatur ulang. Jika Anda tidak melakukan ini, segera hubungi pengelola portal.',
                'Masuk', '/login',
            ),
            'identity.password-changed' => $simple(
                'Kata sandi akun diubah',
                'Kata Sandi Diubah',
                'Kata sandi akun Anda telah diubah. Jika Anda tidak melakukan ini, segera hubungi pengelola portal.',
                'Masuk', '/login',
            ),
            'identity.role.assigned' => static fn (array $p, self $c): array => [
                'subject' => 'Peran akun diperbarui',
                'body' => $c->layout('Peran Ditambahkan', 'Sebuah peran ditambahkan ke akun Anda di Portal Karir.', ['Peran' => $p['role_code'] ?? null], 'Buka Portal Karir', self::url('/dashboard')),
            ],
            'identity.role.revoked' => static fn (array $p, self $c): array => [
                'subject' => 'Peran akun diperbarui',
                'body' => $c->layout('Peran Dicabut', 'Sebuah peran dicabut dari akun Anda di Portal Karir.', ['Peran' => $p['role_code'] ?? null], 'Buka Portal Karir', self::url('/dashboard')),
            ],

            // --- Company membership & verification ---
            'company.member.invited' => static fn (array $p, self $c): array => [
                'subject' => 'Anda ditambahkan ke perusahaan',
                'body' => $c->layout('Keanggotaan Perusahaan', 'Anda ditambahkan sebagai anggota sebuah perusahaan di Portal Karir.', ['Peran perusahaan' => $p['company_role'] ?? null], 'Buka Portal Karir', self::url('/dashboard')),
            ],
            'company.verification.SUBMITTED' => $simple('Perusahaan diajukan untuk verifikasi', 'Verifikasi Perusahaan Diajukan', 'Profil perusahaan Anda telah diajukan untuk verifikasi oleh Career Center.', 'Status Verifikasi', '/status-verifikasi'),
            'company.verification.VERIFY' => $simple('Perusahaan terverifikasi', 'Perusahaan Terverifikasi', 'Perusahaan Anda telah diverifikasi. Anda kini dapat membuat lowongan.', 'Kelola Lowongan', '/kelola-lowongan'),
            'company.verification.REQUEST_REVISION' => $simple('Perusahaan perlu perbaikan', 'Perlu Perbaikan', 'Career Center meminta perbaikan pada profil perusahaan Anda sebelum verifikasi dapat dilanjutkan.', 'Status Verifikasi', '/status-verifikasi'),
            'company.verification.REJECT' => $simple('Verifikasi perusahaan ditolak', 'Verifikasi Ditolak', 'Pengajuan verifikasi perusahaan Anda ditolak oleh Career Center.', 'Status Verifikasi', '/status-verifikasi'),
            'company.verification.SUSPEND' => $simple('Perusahaan ditangguhkan', 'Perusahaan Ditangguhkan', 'Perusahaan Anda ditangguhkan oleh Career Center. Lowongan yang dipublikasikan mungkin tidak lagi tampil kepada publik.', 'Status Verifikasi', '/status-verifikasi'),
            'company.verification.RESTORE' => $simple('Perusahaan dipulihkan', 'Perusahaan Dipulihkan', 'Penangguhan perusahaan Anda telah dicabut oleh Career Center.', 'Status Verifikasi', '/status-verifikasi'),

            // --- Vacancy lifecycle ---
            'vacancy.lifecycle.submit' => self::vacancyLifecycle('Lowongan diajukan untuk moderasi', 'Lowongan Diajukan', 'Sebuah lowongan diajukan untuk moderasi Career Center.'),
            'vacancy.lifecycle.approve' => self::vacancyLifecycle('Lowongan disetujui', 'Lowongan Disetujui', 'Sebuah lowongan telah disetujui.'),
            'vacancy.lifecycle.request_revision' => self::vacancyLifecycle('Lowongan perlu perbaikan', 'Lowongan Perlu Perbaikan', 'Career Center meminta perbaikan pada sebuah lowongan.'),
            'vacancy.lifecycle.reject' => self::vacancyLifecycle('Lowongan ditolak', 'Lowongan Ditolak', 'Sebuah lowongan ditolak oleh Career Center.'),
            'vacancy.lifecycle.publish' => self::vacancyLifecycle('Lowongan dipublikasikan', 'Lowongan Dipublikasikan', 'Sebuah lowongan telah dipublikasikan.'),
            'vacancy.lifecycle.close' => self::vacancyLifecycle('Lowongan ditutup', 'Lowongan Ditutup', 'Sebuah lowongan telah ditutup.'),
            'vacancy.lifecycle.suspend' => self::vacancyLifecycle('Lowongan ditangguhkan', 'Lowongan Ditangguhkan', 'Sebuah lowongan telah ditangguhkan.'),
            'vacancy.lifecycle.restore' => self::vacancyLifecycle('Lowongan dipulihkan', 'Lowongan Dipulihkan', 'Penangguhan sebuah lowongan telah dicabut.'),
            'vacancy.moderation.queue' => self::vacancyLifecycle('Lowongan baru menunggu moderasi', 'Antrean Moderasi', 'Sebuah lowongan baru menunggu moderasi Career Center.', '/moderasi-lowongan', 'Moderasi Lowongan'),

            // --- Application ---
            'application.submitted.candidate' => self::application('Lamaran Anda telah dikirim', 'Lamaran Terkirim', 'Lamaran Anda telah berhasil dikirim ke pemilik lowongan.', '/lamaran-saya', 'Lamaran Saya'),
            'application.submitted.owner' => self::application('Pelamar baru untuk lowongan Anda', 'Pelamar Baru', 'Ada pelamar baru untuk salah satu lowongan Anda.', '/pelamar', 'Pelamar'),
            'application.withdrawn.candidate' => self::application('Lamaran Anda ditarik', 'Lamaran Ditarik', 'Lamaran Anda telah ditarik.', '/lamaran-saya', 'Lamaran Saya'),
            'application.withdrawn.owner' => self::application('Seorang pelamar menarik lamarannya', 'Pelamar Menarik Lamaran', 'Seorang pelamar telah menarik lamarannya dari salah satu lowongan Anda.', '/pelamar', 'Pelamar'),
            'application.transitioned.candidate' => self::application('Status lamaran Anda berubah', 'Status Lamaran Berubah', 'Status salah satu lamaran Anda telah diperbarui oleh pemilik lowongan.', '/lamaran-saya', 'Lamaran Saya'),
            'application.stage_moved.candidate' => self::application('Tahap seleksi lamaran Anda berubah', 'Tahap Seleksi Berubah', 'Tahap seleksi salah satu lamaran Anda telah diperbarui.', '/lamaran-saya', 'Lamaran Saya'),

            // --- Selection schedule ---
            'schedule.created.candidate' => self::schedule('Jadwal seleksi dibuat', 'Jadwal Seleksi', 'Sebuah jadwal seleksi telah dibuat untuk lamaran Anda.', '/jadwal-seleksi'),
            'schedule.created.pic' => self::schedule('Jadwal seleksi dibuat', 'Jadwal Seleksi', 'Anda ditetapkan sebagai PIC pada sebuah jadwal seleksi.', '/jadwal-seleksi'),
            'schedule.rescheduled.candidate' => self::schedule('Jadwal seleksi diubah', 'Jadwal Seleksi Diubah', 'Sebuah jadwal seleksi untuk lamaran Anda telah dijadwalkan ulang.', '/jadwal-seleksi'),
            'schedule.rescheduled.pic' => self::schedule('Jadwal seleksi diubah', 'Jadwal Seleksi Diubah', 'Sebuah jadwal seleksi yang Anda tangani telah dijadwalkan ulang.', '/jadwal-seleksi'),
            'schedule.cancelled.candidate' => self::schedule('Jadwal seleksi dibatalkan', 'Jadwal Seleksi Dibatalkan', 'Sebuah jadwal seleksi untuk lamaran Anda telah dibatalkan.', '/jadwal-seleksi'),
            'schedule.cancelled.pic' => self::schedule('Jadwal seleksi dibatalkan', 'Jadwal Seleksi Dibatalkan', 'Sebuah jadwal seleksi yang Anda tangani telah dibatalkan.', '/jadwal-seleksi'),

            // --- Evaluation ---
            'evaluation.submitted.owner' => self::application('Evaluasi kandidat difinalisasi', 'Evaluasi Difinalisasi', 'Sebuah evaluasi kandidat telah difinalisasi.', '/pelamar', 'Pelamar'),

            // --- Offering ---
            'offer.sent.candidate' => self::application('Anda menerima penawaran', 'Penawaran Diterbitkan', 'Anda menerima sebuah penawaran (offering) untuk sebuah lowongan.', '/lamaran-saya', 'Lamaran Saya'),
            'offer.accepted.candidate' => self::application('Penawaran diterima', 'Penawaran Diterima', 'Anda telah menerima sebuah penawaran.', '/lamaran-saya', 'Lamaran Saya'),
            'offer.accepted.owner' => self::application('Kandidat menerima penawaran', 'Penawaran Diterima Kandidat', 'Seorang kandidat telah menerima penawaran Anda.', '/pelamar', 'Pelamar'),
            'offer.rejected.owner' => self::application('Kandidat menolak penawaran', 'Penawaran Ditolak Kandidat', 'Seorang kandidat telah menolak penawaran Anda.', '/pelamar', 'Pelamar'),

            // --- Selector assignment ---
            'selection.selector.assignment.assigned' => static fn (array $p, self $c): array => [
                'subject' => 'Anda ditugaskan sebagai selektor',
                'body' => $c->layout('Penugasan Selektor', 'Anda ditugaskan sebagai selektor pada sebuah tahap seleksi.', ['ID tahap' => $p['recruitment_stage_id'] ?? null], 'Buka Portal Karir', self::url('/dashboard')),
            ],
            'selection.selector.assignment.revoked' => static fn (array $p, self $c): array => [
                'subject' => 'Penugasan selektor dicabut',
                'body' => $c->layout('Penugasan Selektor Dicabut', 'Penugasan selektor Anda pada sebuah tahap seleksi telah dicabut.', ['ID tahap' => $p['recruitment_stage_id'] ?? null], 'Buka Portal Karir', self::url('/dashboard')),
            ],
        ];
    }

    /** @return callable(array<string, mixed>, self): array{subject: string, body: string} */
    private static function vacancyLifecycle(string $subject, string $title, string $desc, string $path = '/kelola-lowongan', string $label = 'Kelola Lowongan'): callable
    {
        return static fn (array $p, self $c): array => [
            'subject' => $subject,
            'body' => $c->layout($title, $desc, [
                'ID lowongan' => $p['vacancy_id'] ?? null,
                'Status' => $p['status'] ?? null,
                'Catatan' => $p['recruiter_visible_note'] ?? null,
            ], $label, self::url($path)),
        ];
    }

    /** @return callable(array<string, mixed>, self): array{subject: string, body: string} */
    private static function application(string $subject, string $title, string $desc, string $path, string $label): callable
    {
        return static fn (array $p, self $c): array => [
            'subject' => $subject,
            'body' => $c->layout($title, $desc, [
                'ID lamaran' => $p['application_id'] ?? null,
                'ID lowongan' => $p['vacancy_id'] ?? null,
            ], $label, self::url($path)),
        ];
    }

    /** @return callable(array<string, mixed>, self): array{subject: string, body: string} */
    private static function schedule(string $subject, string $title, string $desc, string $path): callable
    {
        return static fn (array $p, self $c): array => [
            'subject' => $subject,
            'body' => $c->layout($title, $desc, [
                'ID jadwal' => $p['schedule_id'] ?? null,
                'Metode' => $p['method'] ?? null,
                'Mulai' => $p['starts_at'] ?? null,
                'Zona waktu' => $p['timezone'] ?? null,
            ], 'Jadwal Seleksi', self::url($path)),
        ];
    }
}
