# Stitch Screen Mapping

Traceability from the original Google Stitch export folder names to their location in this
repository. **Nothing in the Stitch export was deleted.** Every original folder appears in
exactly one row below.

- Original export archive: `archive/packages/stitch_campus_career_portal_system_original.zip`
- Original export root inside the ZIP: `stitch_campus_career_portal_system/`
- Files verified after the move: **229 / 229 byte-identical** (MD5 set comparison against the ZIP).

Legend for **Destination**:

| Prefix | Meaning |
| --- | --- |
| `design/stitch/…` | Canonical reference screen: either a single exported version or a reversible copy selected from preserved iterations. |
| `archive/stitch-iterations/…` | Preserved original exported iteration. A selected iteration can also have a reversible canonical copy in `design/stitch/`; see Current canonical selection below. |
| `archive/stitch-duplicates/…` | Byte-identical copy of another export folder. |

---

## Mapping table

| Original Folder | New Folder | Role | Screen Purpose |
| --- | --- | --- | --- |
| `academic_career_nexus` | `design/stitch/design-system/` | design-system | Stitch design system tokens (colors, typography) for theme "Academic Career Nexus" |
| `beranda_portal_karir_kampus_1` | `archive/stitch-iterations/public/beranda/iteration-1/` | public | Public landing page / Beranda |
| `beranda_portal_karir_kampus_2` | `archive/stitch-iterations/public/beranda/iteration-2/` | public | Public landing page / Beranda (no screen.png in export) |
| `daftar_lowongan_portal_karir_kampus` | `design/stitch/public/daftar-lowongan/` | public | Public vacancy list with filters |
| `detail_lowongan_portal_karir_kampus` | `design/stitch/public/detail-lowongan/` | public | Public vacancy detail |
| `detail_lowongan_external_apply_portal_karir_kampus_1` | `archive/stitch-iterations/public/detail-lowongan-external-apply/iteration-1/` | public | Public vacancy detail for externally-applied vacancy |
| `detail_lowongan_external_apply_portal_karir_kampus_2` | `archive/stitch-iterations/public/detail-lowongan-external-apply/iteration-2/` | public | Public vacancy detail for externally-applied vacancy |
| `konfirmasi_external_apply_portal_karir_kampus` | `design/stitch/public/konfirmasi-external-apply/` | public | Confirmation interstitial before leaving to external apply URL |
| `daftar_akun_kandidat_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/daftar-akun-kandidat/iteration-1/` | candidate | Candidate account registration |
| `daftar_akun_kandidat_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/daftar-akun-kandidat/iteration-2/` | candidate | Candidate account registration |
| `daftar_akun_kandidat_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/daftar-akun-kandidat/iteration-3/` | candidate | Candidate account registration |
| `daftar_akun_kandidat_portal_karir_kampus_4` | `archive/stitch-iterations/candidate/daftar-akun-kandidat/iteration-4/` | candidate | Candidate account registration |
| `portal_karir_mahasiswa` | `archive/stitch-duplicates/candidate/daftar-akun-kandidat/portal_karir_mahasiswa/` | candidate | EXACT byte-identical copy of daftar_akun_kandidat_portal_karir_kampus_4 (md5 b71d4f84) |
| `verifikasi_email_anda_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/verifikasi-email/iteration-1/` | candidate | Email verification prompt screen |
| `verifikasi_email_anda_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/verifikasi-email/iteration-2/` | candidate | Email verification prompt screen |
| `verifikasi_email_anda_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/verifikasi-email/iteration-3/` | candidate | Email verification prompt screen |
| `verifikasi_email_anda_portal_karir_kampus_4` | `archive/stitch-iterations/candidate/verifikasi-email/iteration-4/` | candidate | Email verification prompt screen |
| `verifikasi_email_dikirim_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/verifikasi-email-dikirim/iteration-1/` | candidate | Verification email sent confirmation |
| `verifikasi_email_dikirim_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/verifikasi-email-dikirim/iteration-2/` | candidate | Verification email sent confirmation |
| `dashboard_kandidat_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/dashboard-kandidat/iteration-1/` | candidate | Candidate dashboard / home |
| `dashboard_kandidat_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/dashboard-kandidat/iteration-2/` | candidate | Candidate dashboard / home |
| `dashboard_kandidat_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/dashboard-kandidat/iteration-3/` | candidate | Candidate dashboard / home |
| `dashboard_kandidat_portal_karir_kampus_4` | `archive/stitch-iterations/candidate/dashboard-kandidat/iteration-4/` | candidate | Candidate dashboard / home |
| `dashboard_kandidat_portal_karir_kampus_5` | `archive/stitch-iterations/candidate/dashboard-kandidat/iteration-5/` | candidate | Candidate dashboard / home |
| `profil_saya_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/profil-saya/iteration-1/` | candidate | Candidate profile view/edit |
| `profil_saya_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/profil-saya/iteration-2/` | candidate | Candidate profile view/edit |
| `profil_saya_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/profil-saya/iteration-3/` | candidate | Candidate profile view/edit |
| `profil_saya_portal_karir_kampus_4` | `archive/stitch-iterations/candidate/profil-saya/iteration-4/` | candidate | Candidate profile view/edit |
| `verifikasi_alumni_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/verifikasi-alumni/iteration-1/` | candidate | Alumni status verification request |
| `verifikasi_alumni_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/verifikasi-alumni/iteration-2/` | candidate | Alumni status verification request |
| `verifikasi_status_alumni_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/verifikasi-status-alumni/iteration-1/` | candidate | Alumni status verification (separate Stitch naming; overlaps verifikasi-alumni) |
| `verifikasi_status_alumni_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/verifikasi-status-alumni/iteration-2/` | candidate | Alumni status verification (separate Stitch naming; overlaps verifikasi-alumni) |
| `cv_dokumen_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/cv-dokumen/iteration-1/` | candidate | CV and document management |
| `cv_dokumen_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/cv-dokumen/iteration-2/` | candidate | CV and document management |
| `cv_dokumen_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/cv-dokumen/iteration-3/` | candidate | CV and document management |
| `lamar_lowongan_periksa_profil_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/lamar-lowongan-periksa-profil/iteration-1/` | candidate | Apply flow step 1 - review profile |
| `lamar_lowongan_periksa_profil_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/lamar-lowongan-periksa-profil/iteration-2/` | candidate | Apply flow step 1 - review profile |
| `lamar_lowongan_periksa_profil_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/lamar-lowongan-periksa-profil/iteration-3/` | candidate | Apply flow step 1 - review profile |
| `lamar_lowongan_pilih_dokumen_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/lamar-lowongan-pilih-dokumen/iteration-1/` | candidate | Apply flow step 2 - select documents |
| `lamar_lowongan_pilih_dokumen_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/lamar-lowongan-pilih-dokumen/iteration-2/` | candidate | Apply flow step 2 - select documents |
| `lamar_lowongan_pilih_dokumen_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/lamar-lowongan-pilih-dokumen/iteration-3/` | candidate | Apply flow step 2 - select documents |
| `lamar_lowongan_pertanyaan_seleksi_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/lamar-lowongan-pertanyaan-seleksi/iteration-1/` | candidate | Apply flow step 3 - screening questions |
| `lamar_lowongan_pertanyaan_seleksi_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/lamar-lowongan-pertanyaan-seleksi/iteration-2/` | candidate | Apply flow step 3 - screening questions |
| `lamar_lowongan_pertanyaan_seleksi_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/lamar-lowongan-pertanyaan-seleksi/iteration-3/` | candidate | Apply flow step 3 - screening questions |
| `lamar_lowongan_preview_kirim_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/lamar-lowongan-preview-kirim/iteration-1/` | candidate | Apply flow step 4 - preview and submit |
| `lamar_lowongan_preview_kirim_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/lamar-lowongan-preview-kirim/iteration-2/` | candidate | Apply flow step 4 - preview and submit |
| `lamar_lowongan_preview_kirim_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/lamar-lowongan-preview-kirim/iteration-3/` | candidate | Apply flow step 4 - preview and submit |
| `lamaran_berhasil_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/lamaran-berhasil/iteration-1/` | candidate | Application submitted success |
| `lamaran_berhasil_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/lamaran-berhasil/iteration-2/` | candidate | Application submitted success |
| `lamaran_berhasil_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/lamaran-berhasil/iteration-3/` | candidate | Application submitted success |
| `sudah_melamar_portal_karir_kampus` | `design/stitch/candidate/sudah-melamar/` | candidate | Duplicate-application guard state |
| `lamaran_saya_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/lamaran-saya/iteration-1/` | candidate | My applications list |
| `lamaran_saya_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/lamaran-saya/iteration-2/` | candidate | My applications list |
| `lamaran_saya_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/lamaran-saya/iteration-3/` | candidate | My applications list |
| `lamaran_saya_portal_karir_kampus_4` | `archive/stitch-iterations/candidate/lamaran-saya/iteration-4/` | candidate | My applications list |
| `detail_lamaran_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/detail-lamaran/iteration-1/` | candidate | Application detail and status timeline |
| `detail_lamaran_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/detail-lamaran/iteration-2/` | candidate | Application detail and status timeline |
| `detail_lamaran_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/detail-lamaran/iteration-3/` | candidate | Application detail and status timeline |
| `detail_lamaran_portal_karir_kampus_4` | `archive/stitch-iterations/candidate/detail-lamaran/iteration-4/` | candidate | Application detail and status timeline |
| `detail_offering_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/detail-offering/iteration-1/` | candidate | Offering letter detail (candidate side) |
| `detail_offering_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/detail-offering/iteration-2/` | candidate | Offering letter detail (candidate side) |
| `detail_offering_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/detail-offering/iteration-3/` | candidate | Offering letter detail (candidate side) |
| `detail_offering_portal_karir_kampus_4` | `archive/stitch-iterations/candidate/detail-offering/iteration-4/` | candidate | Offering letter detail (candidate side) |
| `offering_diterima_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/offering-diterima/iteration-1/` | candidate | Offering accepted confirmation |
| `offering_diterima_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/offering-diterima/iteration-2/` | candidate | Offering accepted confirmation |
| `konfirmasi_pengunduran_diri_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/konfirmasi-pengunduran-diri/iteration-1/` | candidate | Withdrawal confirmation modal |
| `konfirmasi_pengunduran_diri_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/konfirmasi-pengunduran-diri/iteration-2/` | candidate | Withdrawal confirmation modal |
| `konfirmasi_pengunduran_diri_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/konfirmasi-pengunduran-diri/iteration-3/` | candidate | Withdrawal confirmation modal |
| `konfirmasi_pengunduran_diri_portal_karir_kampus_4` | `archive/stitch-iterations/candidate/konfirmasi-pengunduran-diri/iteration-4/` | candidate | Withdrawal confirmation modal (rendered inside application detail) |
| `pengunduran_diri_berhasil_portal_karir_kampus` | `design/stitch/candidate/pengunduran-diri-berhasil/` | candidate | Withdrawal success confirmation |
| `aktivitas_lamaran_eksternal_portal_karir_kampus` | `design/stitch/candidate/aktivitas-lamaran-eksternal/` | candidate | External apply activity tracking |
| `lowongan_tersimpan_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/lowongan-tersimpan/iteration-1/` | candidate | Saved vacancies |
| `lowongan_tersimpan_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/lowongan-tersimpan/iteration-2/` | candidate | Saved vacancies |
| `lowongan_tersimpan_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/lowongan-tersimpan/iteration-3/` | candidate | Saved vacancies |
| `lowongan_tersimpan_portal_karir_kampus_4` | `archive/stitch-iterations/candidate/lowongan-tersimpan/iteration-4/` | candidate | Saved vacancies |
| `jadwal_seleksi_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/jadwal-seleksi/iteration-1/` | candidate | Selection schedule list (candidate view) |
| `jadwal_seleksi_portal_karir_kampus_3` | `archive/stitch-iterations/candidate/jadwal-seleksi/iteration-3/` | candidate | Selection schedule list (candidate view) |
| `jadwal_seleksi_portal_karir_kampus_4` | `archive/stitch-iterations/candidate/jadwal-seleksi/iteration-4/` | candidate | Selection schedule list (candidate view) |
| `jadwal_seleksi_portal_karir_kampus_5` | `archive/stitch-iterations/candidate/jadwal-seleksi/iteration-5/` | candidate | Selection schedule list (candidate view) |
| `detail_jadwal_seleksi_portal_karir_kampus` | `design/stitch/candidate/detail-jadwal-seleksi/` | candidate | Selection schedule detail (candidate view) |
| `notifikasi_portal_karir_kampus_1` | `archive/stitch-iterations/candidate/notifikasi/iteration-1/` | candidate | Notification center (candidate view) |
| `notifikasi_portal_karir_kampus_2` | `archive/stitch-iterations/candidate/notifikasi/iteration-2/` | candidate | Notification center (candidate view) |
| `notifikasi_portal_karir_kampus_4` | `archive/stitch-iterations/candidate/notifikasi/iteration-4/` | candidate | Notification center (candidate view) |
| `daftar_recruiter_portal_karir_kampus_1` | `archive/stitch-iterations/recruiter/daftar-recruiter/iteration-1/` | recruiter | Recruiter account registration |
| `daftar_recruiter_portal_karir_kampus_2` | `archive/stitch-iterations/recruiter/daftar-recruiter/iteration-2/` | recruiter | Recruiter account registration |
| `lengkapi_profil_perusahaan_portal_karir_kampus` | `design/stitch/recruiter/lengkapi-profil-perusahaan/` | recruiter | Multi-step company profile completion form |
| `verifikasi_menunggu_portal_karir_kampus` | `design/stitch/recruiter/verifikasi-menunggu/` | recruiter | Company verification pending state |
| `dashboard_recruiter_menunggu_verifikasi_portal_karir_kampus` | `design/stitch/recruiter/dashboard-menunggu-verifikasi/` | recruiter | Recruiter dashboard - awaiting verification |
| `dashboard_recruiter_terverifikasi_portal_karir_kampus` | `design/stitch/recruiter/dashboard-terverifikasi/` | recruiter | Recruiter dashboard - verified |
| `dashboard_recruiter_perlu_perbaikan_portal_karir_kampus` | `design/stitch/recruiter/dashboard-perlu-perbaikan/` | recruiter | Recruiter dashboard - revision required |
| `dashboard_recruiter_ditangguhkan_portal_karir_kampus` | `design/stitch/recruiter/dashboard-ditangguhkan/` | recruiter | Recruiter dashboard - access suspended |
| `dashboard_recruiter_penangguhan_portal_karir_kampus` | `design/stitch/recruiter/dashboard-penangguhan/` | recruiter | Recruiter dashboard - suspension notice (same page title as dashboard-ditangguhkan) |
| `dashboard_recruiter_portal_karir_kampus` | `design/stitch/recruiter/dashboard-recruiter/` | recruiter | Recruiter dashboard - generic/base variant |
| `revisi_lowongan_portal_karir_kampus_1` | `archive/stitch-iterations/recruiter/revisi-lowongan/iteration-1/` | recruiter | Vacancy revision requested by Career Center |
| `revisi_lowongan_portal_karir_kampus_2` | `archive/stitch-iterations/recruiter/revisi-lowongan/iteration-2/` | recruiter | Vacancy revision requested by Career Center |
| `dashboard_career_center_portal_karir_kampus_1` | `archive/stitch-iterations/career-center/dashboard-career-center/iteration-1/` | career-center | Career Center admin console dashboard |
| `dashboard_career_center_portal_karir_kampus_3` | `archive/stitch-iterations/career-center/dashboard-career-center/iteration-3/` | career-center | Career Center admin console dashboard |
| `verifikasi_perusahaan_portal_karir_kampus` | `design/stitch/career-center/verifikasi-perusahaan/` | career-center | Company verification queue |
| `tinjau_perusahaan_portal_karir_kampus_1` | `archive/stitch-iterations/career-center/tinjau-perusahaan/iteration-1/` | career-center | Company review detail and decision |
| `tinjau_perusahaan_portal_karir_kampus_2` | `archive/stitch-iterations/career-center/tinjau-perusahaan/iteration-2/` | career-center | Company review detail and decision |
| `moderasi_lowongan_portal_karir_kampus` | `design/stitch/career-center/moderasi-lowongan/` | career-center | Vacancy moderation queue |
| `tinjau_lowongan_portal_karir_kampus_1` | `archive/stitch-iterations/career-center/tinjau-lowongan/iteration-1/` | career-center | Vacancy review detail and decision |
| `tinjau_lowongan_portal_karir_kampus_2` | `archive/stitch-iterations/career-center/tinjau-lowongan/iteration-2/` | career-center | Vacancy review detail and decision |
| `dashboard_career_center_portal_karir_kampus_2` | `design/stitch/kepegawaian/dashboard-kepegawaian/` | kepegawaian | Kepegawaian dashboard (mis-filed under career_center folder name in Stitch export) |
| `lowongan_kampus_portal_karir_kampus` | `design/stitch/kepegawaian/lowongan-kampus/` | kepegawaian | Campus vacancy list |
| `buat_lowongan_kampus_portal_karir_kampus` | `design/stitch/kepegawaian/buat-lowongan-kampus/` | kepegawaian | Create campus vacancy - position information |
| `detail_lowongan_kampus_portal_karir_kampus` | `design/stitch/kepegawaian/detail-lowongan-kampus/` | kepegawaian | Campus vacancy detail (admin) |
| `daftar_pelamar_portal_karir_kampus` | `design/stitch/kepegawaian/daftar-pelamar/` | kepegawaian | Applicant list for a campus vacancy |
| `detail_pelamar_portal_karir_kampus` | `design/stitch/kepegawaian/detail-pelamar/` | kepegawaian | Applicant/candidate detail (admin) |
| `jadwal_seleksi_portal_karir_kampus_2` | `design/stitch/kepegawaian/jadwal-seleksi/` | kepegawaian | Selection schedule management (admin view) |
| `penilaian_kandidat_portal_karir_kampus` | `design/stitch/kepegawaian/penilaian-kandidat/` | kepegawaian | Candidate evaluation/scoring |
| `offering_portal_karir_kampus` | `design/stitch/kepegawaian/offering/` | kepegawaian | Offering management (admin) |
| `laporan_portal_karir_kampus_1` | `archive/stitch-iterations/kepegawaian/laporan/iteration-1/` | kepegawaian | Kepegawaian reports |
| `laporan_portal_karir_kampus_2` | `archive/stitch-iterations/kepegawaian/laporan/iteration-2/` | kepegawaian | Kepegawaian reports |
| `notifikasi_portal_karir_kampus_3` | `design/stitch/kepegawaian/notifikasi/` | kepegawaian | Notification center (kepegawaian admin view) |

---

## Current canonical selection

The original organization correctly preserved every ambiguous export. A later BRD/FSD-led review selected 22 high-confidence canonical copies. On 2026-08-24, the remaining seven groups were replaced by the Final Stitch Redesign; their legacy iteration directories were removed only after the redesigned code.html and screen.png files were validated. The original raw Stitch ZIP remains the restore point, and the notes below document the historical pre-selection state.

| Role | Screen | Selected iteration | Canonical copy |
| --- | --- | --- | --- |
| candidate | cv-dokumen | iteration-3 | design/stitch/candidate/cv-dokumen/ |
| candidate | daftar-akun-kandidat | iteration-1 | design/stitch/candidate/daftar-akun-kandidat/ |
| candidate | dashboard-kandidat | iteration-1 | design/stitch/candidate/dashboard-kandidat/ |
| candidate | detail-lamaran | iteration-2 | design/stitch/candidate/detail-lamaran/ |
| candidate | detail-offering | iteration-3 | design/stitch/candidate/detail-offering/ |
| candidate | konfirmasi-pengunduran-diri | iteration-1 | design/stitch/candidate/konfirmasi-pengunduran-diri/ |
| candidate | lamar-lowongan-periksa-profil | iteration-2 | design/stitch/candidate/lamar-lowongan-periksa-profil/ |
| candidate | lamar-lowongan-pilih-dokumen | iteration-2 | design/stitch/candidate/lamar-lowongan-pilih-dokumen/ |
| candidate | lamar-lowongan-preview-kirim | iteration-2 | design/stitch/candidate/lamar-lowongan-preview-kirim/ |
| candidate | lamaran-berhasil | iteration-1 | design/stitch/candidate/lamaran-berhasil/ |
| candidate | lowongan-tersimpan | iteration-3 | design/stitch/candidate/lowongan-tersimpan/ |
| candidate | notifikasi | iteration-4 | design/stitch/candidate/notifikasi/ |
| candidate | offering-diterima | iteration-2 | design/stitch/candidate/offering-diterima/ |
| candidate | profil-saya | iteration-4 | design/stitch/candidate/profil-saya/ |
| candidate | verifikasi-email-dikirim | iteration-1 | design/stitch/candidate/verifikasi-email-dikirim/ |
| candidate | verifikasi-status-alumni | iteration-2 | design/stitch/candidate/verifikasi-status-alumni/ |
| career-center | tinjau-perusahaan | iteration-1 | design/stitch/career-center/tinjau-perusahaan/ |
| kepegawaian | laporan | iteration-1 | design/stitch/kepegawaian/laporan/ |
| public | beranda | iteration-1 | design/stitch/public/beranda/ |
| public | detail-lowongan-external-apply | iteration-1 | design/stitch/public/detail-lowongan-external-apply/ |
| recruiter | daftar-recruiter | iteration-1 | design/stitch/recruiter/daftar-recruiter/ |
| recruiter | revisi-lowongan | iteration-2 | design/stitch/recruiter/revisi-lowongan/ |

### Final Stitch Redesign

| Role | Screen | Active mapping | Canonical path |
| --- | --- | --- | --- |
| candidate | jadwal-seleksi | CANONICAL — FINAL STITCH REDESIGN | design/stitch/candidate/jadwal-seleksi/ |
| candidate | lamar-lowongan-pertanyaan-seleksi | CANONICAL — FINAL STITCH REDESIGN | design/stitch/candidate/lamar-lowongan-pertanyaan-seleksi/ |
| candidate | lamaran-saya | CANONICAL — FINAL STITCH REDESIGN | design/stitch/candidate/lamaran-saya/ |
| candidate | verifikasi-email | CANONICAL — FINAL STITCH REDESIGN | design/stitch/candidate/verifikasi-email/ |
| candidate | verifikasi-alumni | CANONICAL — FINAL STITCH REDESIGN | design/stitch/candidate/verifikasi-alumni/ |
| career-center | dashboard-career-center | CANONICAL — FINAL STITCH REDESIGN | design/stitch/career-center/dashboard-career-center/ |
| career-center | tinjau-lowongan | CANONICAL — FINAL STITCH REDESIGN | design/stitch/career-center/tinjau-lowongan/ |

### Resolved legacy selection state

The seven Final Stitch Redesign screens supersede the former human-review and no-suitable-version entries. Their 21 legacy iteration directories (42 files) were removed after redesign validation; no unrelated archive directory was changed.

See design/stitch/CANONICAL_SELECTION_REPORT.md for per-iteration scoring and rationale.

---

## Original ambiguity — pre-selection archive inventory

29 screens were exported by Stitch in more than one version. The canonical/latest version
**could not be determined with confidence**, therefore **all** iterations of these screens were
kept in `archive/stitch-iterations/` and none was promoted into `design/stitch/`.

Reasons confidence was low:

1. **No chronological signal.** Every file in the export shares one identical timestamp
   (`2026-08-23 14:49`), inside the ZIP as well as on disk.
2. **Parallel explorations, not a refinement chain.** Iterations of the same screen carry
   different product branding in `<title>` ("Universitas Career" / "Career Portal" /
   "Portal Karir" / "Portal Karir Kampus"), so a higher `_N` suffix cannot be assumed newer.
3. **No design-system discriminator.** All iterations use the same Academic Career Nexus tokens.
4. **No screen inventory in the approved documents.** Neither BRD v1.1 nor FSD v1.1 names a
   chosen screen version.

Each affected screen has a `PENDING_SELECTION.md` placeholder in its `design/stitch/` folder
describing how to promote the chosen iteration.

| Role | Screen | Iterations | Archive location |
| --- | --- | --- | --- |
| candidate | `cv-dokumen` | 3 (`iteration-1`, `iteration-2`, `iteration-3`) | `archive/stitch-iterations/candidate/cv-dokumen/` |
| candidate | `daftar-akun-kandidat` | 4 (`iteration-1`, `iteration-2`, `iteration-3`, `iteration-4`) | `archive/stitch-iterations/candidate/daftar-akun-kandidat/` |
| candidate | `dashboard-kandidat` | 5 (`iteration-1`, `iteration-2`, `iteration-3`, `iteration-4`, `iteration-5`) | `archive/stitch-iterations/candidate/dashboard-kandidat/` |
| candidate | `detail-lamaran` | 4 (`iteration-1`, `iteration-2`, `iteration-3`, `iteration-4`) | `archive/stitch-iterations/candidate/detail-lamaran/` |
| candidate | `detail-offering` | 4 (`iteration-1`, `iteration-2`, `iteration-3`, `iteration-4`) | `archive/stitch-iterations/candidate/detail-offering/` |
| candidate | `jadwal-seleksi` | 4 (`iteration-1`, `iteration-3`, `iteration-4`, `iteration-5`) | `archive/stitch-iterations/candidate/jadwal-seleksi/` |
| candidate | `konfirmasi-pengunduran-diri` | 4 (`iteration-1`, `iteration-2`, `iteration-3`, `iteration-4`) | `archive/stitch-iterations/candidate/konfirmasi-pengunduran-diri/` |
| candidate | `lamar-lowongan-periksa-profil` | 3 (`iteration-1`, `iteration-2`, `iteration-3`) | `archive/stitch-iterations/candidate/lamar-lowongan-periksa-profil/` |
| candidate | `lamar-lowongan-pertanyaan-seleksi` | 3 (`iteration-1`, `iteration-2`, `iteration-3`) | `archive/stitch-iterations/candidate/lamar-lowongan-pertanyaan-seleksi/` |
| candidate | `lamar-lowongan-pilih-dokumen` | 3 (`iteration-1`, `iteration-2`, `iteration-3`) | `archive/stitch-iterations/candidate/lamar-lowongan-pilih-dokumen/` |
| candidate | `lamar-lowongan-preview-kirim` | 3 (`iteration-1`, `iteration-2`, `iteration-3`) | `archive/stitch-iterations/candidate/lamar-lowongan-preview-kirim/` |
| candidate | `lamaran-berhasil` | 3 (`iteration-1`, `iteration-2`, `iteration-3`) | `archive/stitch-iterations/candidate/lamaran-berhasil/` |
| candidate | `lamaran-saya` | 4 (`iteration-1`, `iteration-2`, `iteration-3`, `iteration-4`) | `archive/stitch-iterations/candidate/lamaran-saya/` |
| candidate | `lowongan-tersimpan` | 4 (`iteration-1`, `iteration-2`, `iteration-3`, `iteration-4`) | `archive/stitch-iterations/candidate/lowongan-tersimpan/` |
| candidate | `notifikasi` | 3 (`iteration-1`, `iteration-2`, `iteration-4`) | `archive/stitch-iterations/candidate/notifikasi/` |
| candidate | `offering-diterima` | 2 (`iteration-1`, `iteration-2`) | `archive/stitch-iterations/candidate/offering-diterima/` |
| candidate | `profil-saya` | 4 (`iteration-1`, `iteration-2`, `iteration-3`, `iteration-4`) | `archive/stitch-iterations/candidate/profil-saya/` |
| candidate | `verifikasi-alumni` | 2 (`iteration-1`, `iteration-2`) | `archive/stitch-iterations/candidate/verifikasi-alumni/` |
| candidate | `verifikasi-email` | 4 (`iteration-1`, `iteration-2`, `iteration-3`, `iteration-4`) | `archive/stitch-iterations/candidate/verifikasi-email/` |
| candidate | `verifikasi-email-dikirim` | 2 (`iteration-1`, `iteration-2`) | `archive/stitch-iterations/candidate/verifikasi-email-dikirim/` |
| candidate | `verifikasi-status-alumni` | 2 (`iteration-1`, `iteration-2`) | `archive/stitch-iterations/candidate/verifikasi-status-alumni/` |
| career-center | `dashboard-career-center` | 2 (`iteration-1`, `iteration-3`) | `archive/stitch-iterations/career-center/dashboard-career-center/` |
| career-center | `tinjau-lowongan` | 2 (`iteration-1`, `iteration-2`) | `archive/stitch-iterations/career-center/tinjau-lowongan/` |
| career-center | `tinjau-perusahaan` | 2 (`iteration-1`, `iteration-2`) | `archive/stitch-iterations/career-center/tinjau-perusahaan/` |
| kepegawaian | `laporan` | 2 (`iteration-1`, `iteration-2`) | `archive/stitch-iterations/kepegawaian/laporan/` |
| public | `beranda` | 2 (`iteration-1`, `iteration-2`) | `archive/stitch-iterations/public/beranda/` |
| public | `detail-lowongan-external-apply` | 2 (`iteration-1`, `iteration-2`) | `archive/stitch-iterations/public/detail-lowongan-external-apply/` |
| recruiter | `daftar-recruiter` | 2 (`iteration-1`, `iteration-2`) | `archive/stitch-iterations/recruiter/daftar-recruiter/` |
| recruiter | `revisi-lowongan` | 2 (`iteration-1`, `iteration-2`) | `archive/stitch-iterations/recruiter/revisi-lowongan/` |

---

## Reclassified screens

Three exported folders were filed under a folder name that does not match the role the screen
actually serves (confirmed from the page `<title>` and page content). They were placed under the
correct role and are recorded here so the original naming is not lost.

| Original Folder | Filed under role | Evidence |
| --- | --- | --- |
| `dashboard_career_center_portal_karir_kampus_2` | `kepegawaian` | Page title is `Dashboard Kepegawaian - Portal Karir`, not a Career Center console. Because it was removed from the Career Center group, `dashboard-career-center` has iterations 1 and 3 only. |
| `jadwal_seleksi_portal_karir_kampus_2` | `kepegawaian` | Page title is `Jadwal Seleksi - Admin Kepegawaian` (schedule *management*), while iterations 1, 3, 4, 5 are the candidate-facing schedule list. `jadwal-seleksi` therefore has candidate iterations 1, 3, 4, 5 and a single unique kepegawaian screen. |
| `notifikasi_portal_karir_kampus_3` | `kepegawaian` | Page title is `Notifikasi Kepegawaian - Admin Portal`, while iterations 1, 2, 4 are the candidate notification centre. |

## Exact duplicate

| Original Folder | Duplicate of | Evidence |
| --- | --- | --- |
| `portal_karir_mahasiswa` | `daftar_akun_kandidat_portal_karir_kampus_4` | `code.html` is byte-identical (MD5 `b71d4f84…`), both titled `Daftar Akun Kandidat - Portal Karir`. Archived under `archive/stitch-duplicates/`, **not deleted**. |

## Incomplete exports

| Folder | Issue |
| --- | --- |
| `beranda_portal_karir_kampus_2` | `code.html` present, **`screen.png` missing** in the original export. |
| `portal_karir_mahasiswa` | `code.html` present, **`screen.png` missing** in the original export. |
| `academic_career_nexus` | Contains only `DESIGN.md` (design tokens). It is not a screen; placed in `design/stitch/design-system/`. |

## Screens referenced in planning but absent from the export

These screens are named in the requirements/organization brief but have **no** corresponding Stitch
export folder. They are listed for gap tracking only — nothing was invented to fill them.

- Public: **Login**
- Recruiter: **Buat Lowongan**, **Daftar Pelamar (recruiter)**, recruiter selection screens, recruiter outcome screens
- Career Center: **Kemitraan**, **Alumni & Outcome**, Career Center reports
- Kepegawaian: **Outcome Rekrutmen**

## Overlapping screens requiring review

| Screens | Note |
| --- | --- |
| `recruiter/dashboard-ditangguhkan` and `recruiter/dashboard-penangguhan` | Different folder names and different file content, but the **same page title** (`Akses Perusahaan Ditangguhkan - Career Center`). Both kept; confirm whether these are one screen or two distinct states. |
| `recruiter/dashboard-recruiter` and `recruiter/dashboard-terverifikasi` | Both titled `Recruiter Dashboard - Portal Karir Kampus`. Both kept; confirm whether `dashboard-recruiter` is a generic base or a duplicate of the verified state. |
| `candidate/verifikasi-alumni` and `candidate/verifikasi-status-alumni` | Two separately-named Stitch groups (2 iterations each) that appear to cover the same alumni-verification function. Kept separate; confirm whether they merge into one screen. |
| `public/detail-lowongan`, `public/detail-lowongan-external-apply`, `public/konfirmasi-external-apply` | All three share the title `Detail Lowongan - Portal Karir Kampus`. Kept separate because file contents differ; confirm the intended state split. |
