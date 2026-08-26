# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## Portal Karir Kampus

**Versi:** 1.0-C2
**Tanggal:** 26 Agustus 2026
**Status:** Final Corrected Retrospective Product Baseline / Alignment with BRD-FSD 1.1
**Pemilik Produk:** Perguruan Tinggi
**Unit Bisnis Utama:** Career Center dan Admin Kepegawaian/HR-SDM
**Dokumen Acuan Utama:** BRD Portal Karir Kampus v1.1; FSD Portal Karir Kampus v1.1

---

## 1. Informasi Dokumen

### 1.1 Tujuan PRD

PRD ini mendefinisikan **arah produk, pengguna, masalah yang diselesaikan, ruang lingkup MVP, outcome produk, prinsip pengalaman, kebutuhan produk tingkat tinggi, indikator keberhasilan, risiko, ketergantungan, dan prioritas delivery** untuk Portal Karir Kampus.

Dokumen ini dibuat secara retrospektif setelah BRD/FSD v1.1 dan sejumlah keputusan Product Owner telah dibekukan. Karena itu, PRD ini **tidak dimaksudkan untuk menggantikan atau menulis ulang BRD/FSD**, melainkan menjadi lapisan produk yang memudahkan seluruh tim memahami:

- **mengapa** produk dibangun;
- **untuk siapa** produk dibangun;
- **masalah apa** yang harus diselesaikan;
- **outcome apa** yang diharapkan;
- **fitur apa** yang termasuk atau tidak termasuk MVP;
- **bagaimana** keberhasilan produk diukur;
- **bagaimana** kebutuhan produk dilacak ke BRD, FSD, keputusan Product Owner, dan implementasi.

### 1.2 Kedudukan Dokumen dan Source of Truth

Urutan otoritas yang digunakan pada proyek ini adalah:

1. **BRD versi approved terkini** — kebenaran kebutuhan bisnis.
2. **FSD versi approved terkini** — kebenaran perilaku fungsional.
3. **Keputusan eksplisit Product Owner yang telah disetujui** — klarifikasi atas hal yang belum deterministik di BRD/FSD.
4. **Kontrak teknis yang telah dibekukan** — API, authorization, database invariants, architecture, security.
5. **Implementasi dan tests**.
6. **Stitch/UI artifacts** — referensi pengalaman visual, bukan sumber business rule.

PRD ini berada pada **lapisan framing produk** (vision, outcome, scope, personas, dan success criteria), sedangkan BRD/FSD tetap menjadi authority kebutuhan bisnis dan perilaku fungsional. PRD tidak boleh mengubah requirement yang telah disetujui secara diam-diam. Bila PRD, BRD, atau FSD berbeda, BRD/FSD dan keputusan Product Owner yang telah dibekukan tetap menjadi authority untuk implementasi sampai change request disetujui.

### 1.3 Sumber Penyusunan

PRD v1.0-C2 disusun dari:

- `BRD_Portal_Karir_Kampus_v1.1.md`;
- `FSD_Portal_Karir_Kampus_v1.1.md`;
- keputusan Product Owner yang telah ditutup selama implementasi;
- milestone implementasi yang telah melewati test, independent audit, dan freeze tag;
- open decisions yang masih belum diselesaikan pada 26 Agustus 2026.

### 1.4 Koreksi C1

Koreksi `1.0-C1` memperjelas empat hal tanpa mengubah BRD/FSD:

- memperbaiki product promise recruiter agar jelas bahwa company `VERIFIED` memberi hak **membuat dan mengajukan** vacancy, sedangkan tayang tetap melalui Career Center moderation;
- membedakan requirement BRD/FSD dengan **Approved Product Owner Decisions** yang menutup ambiguity setelah FSD;
- memisahkan **product/institutional dependencies** dari **technical delivery dependencies**;
- menambahkan baseline **scalability, performance, reliability, capacity planning, dan load-testing** untuk target skala jutaan akun terdaftar tanpa mengklaim jutaan pengguna bersamaan.

### 1.5 Koreksi C2

Koreksi `1.0-C2` menutup temuan final consistency audit tanpa mengubah BRD/FSD maupun frozen lifecycle:

- menghapus satu item open-decision yang tidak memiliki sumber authoritative;
- menegaskan bahwa **O-7 telah CLOSED** dan automatic expiry hanya memproses company vacancy `PUBLISHED`; `SCHEDULED` tidak di-auto-expire oleh operasi O-7;
- memperbaiki Delivery/Milestone Snapshot agar status expiry tetap **IMPLEMENTED / PENDING FINAL FREEZE** hanya karena independent freeze audit/tag belum diselesaikan, bukan karena ada keputusan produk yang masih open;
- memperbaiki Proposed Delivery Sequence agar langkah berikutnya adalah independent audit + freeze expiry, lalu public-discovery semantics;
- memberi label pada regression snapshot sebagai engineering report yang belum menjadi product requirement atau SLA.

---

## 2. Ringkasan Eksekutif

Portal Karir Kampus adalah **kanal karier resmi perguruan tinggi** yang menyatukan dua jalur bisnis dalam satu produk dan satu basis data kandidat:

1. **Karier di Kampus** — rekrutmen pegawai kampus yang dikelola Admin Kepegawaian/HR-SDM.
2. **Karier untuk Alumni** — lowongan kerja/magang dari perusahaan eksternal yang telah diverifikasi Career Center.

Produk mengatasi kondisi ketika lowongan, data pelamar, komunikasi, outcome, dan pelaporan tersebar di media sosial, grup percakapan, email, spreadsheet, formulir umum, dan dokumen terpisah.

Portal harus menjadi tempat resmi untuk menemukan lowongan, mendaftar, mengelola profil kandidat, mengelola perusahaan, membuat dan memoderasi lowongan, melamar, mengelola proses seleksi, offering dan outcome, serta menghasilkan laporan yang dapat diaudit.

Prinsip utamanya adalah **trust, eligibility, traceability, privacy, role separation, dan single lifecycle**: perusahaan harus diverifikasi sebelum membuat lowongan, lowongan perusahaan harus dimoderasi sebelum tayang, dokumen kandidat tetap privat kecuali dibagikan secara eksplisit dalam application, dan seluruh status penting memiliki riwayat serta actor yang dapat ditelusuri.

---

## 3. Problem Statement

### 3.1 Masalah Pengguna dan Institusi

Perguruan tinggi membutuhkan satu sistem resmi karena proses karier dan rekrutmen yang tersebar menimbulkan masalah berikut:

- kandidat sulit mengetahui lowongan mana yang valid dan resmi;
- kandidat tidak mempunyai satu tempat untuk memantau status lamarannya;
- perusahaan berulang kali mengirim data perusahaan dan lowongan ke Career Center;
- Career Center sulit memverifikasi legalitas perusahaan secara konsisten;
- Career Center sulit memastikan lowongan perusahaan telah dimoderasi sebelum tayang;
- Admin Kepegawaian tidak memiliki applicant tracking yang terdokumentasi untuk rekrutmen kampus;
- dokumen kandidat berisiko tersebar tanpa kontrol consent yang jelas;
- proses seleksi, jadwal, offering, dan outcome sulit dilacak end-to-end;
- outcome rekrutmen alumni sulit dikumpulkan;
- pimpinan tidak mempunyai data konsisten untuk mengukur efektivitas rekrutmen, penyerapan alumni, dan hubungan perusahaan;
- notifikasi dan follow-up yang bergantung pada email manual mudah gagal atau terlewat;
- audit actor/status/history tidak tersedia secara konsisten jika proses dijalankan lewat banyak media terpisah.

### 3.2 Opportunity

Portal Karir Kampus dapat menjadi **single source of truth** untuk:

- lowongan resmi kampus dan perusahaan;
- identitas kandidat;
- company verification dan partnership;
- lifecycle lowongan;
- lifecycle application;
- jadwal seleksi;
- offering dan outcome;
- consent dan dokumen yang dibagikan;
- notification history;
- audit trail;
- laporan operasional dan KPI.

---

## 4. Product Vision

> **Menjadi portal karier resmi perguruan tinggi yang terpercaya, terpusat, transparan, dan dapat diaudit untuk mempertemukan kandidat, perusahaan, Career Center, dan pengelola rekrutmen kampus dalam satu lifecycle digital.**

### 4.1 Product Promise

Portal Karir Kampus harus membuat setiap kelompok pengguna mendapatkan nilai yang jelas:

- **Kandidat:** menemukan lowongan yang relevan dan sah, mengendalikan data pribadi yang dibagikan, serta mengetahui progres lamaran.
- **Recruiter:** mempunyai jalur onboarding perusahaan yang jelas, dapat **membuat dan mengajukan lowongan setelah perusahaan terverifikasi**, sementara lowongan baru tayang setelah Career Center moderation; recruiter kemudian mengelola applicant serta outcome dalam satu portal.
- **Career Center:** mempunyai control point untuk verifikasi perusahaan, moderasi lowongan, kemitraan, dan outcome alumni.
- **Admin Kepegawaian:** mempunyai proses rekrutmen kampus yang terdokumentasi sampai offering/outcome tanpa ketergantungan pada spreadsheet terpisah.
- **Pimpinan/Auditor:** memperoleh data dan jejak audit yang dapat dipercaya.
- **Super Admin:** dapat mengelola konfigurasi dan governance platform tanpa mengambil alih kepemilikan bisnis normal tiap domain.

---

## 5. Product Goals

| ID | Product Goal |
|---|---|
| PG-01 | Menjadikan portal sebagai kanal resmi publikasi lowongan kampus dan lowongan perusahaan. |
| PG-02 | Meningkatkan trust dengan memisahkan verifikasi perusahaan, kemitraan, dan moderasi lowongan secara jelas. |
| PG-03 | Memungkinkan kandidat eksternal, mahasiswa tingkat akhir, dan alumni mengelola identitas karier dan melamar secara mandiri. |
| PG-04 | Memungkinkan kandidat memantau application sampai outcome akhir. |
| PG-05 | Menyediakan rekrutmen kampus yang terdokumentasi dan dapat diaudit pada model MVP linear. |
| PG-06 | Mengurangi proses manual melalui email, formulir, dan spreadsheet terpisah. |
| PG-07 | Menghasilkan data penyerapan alumni, funnel rekrutmen, Time-to-Fill, aktivitas perusahaan, dan outcome yang dapat dilaporkan. |
| PG-08 | Menjaga privacy, consent, role separation, object ownership, dan history sebagai prinsip produk, bukan fitur tambahan. |
| PG-09 | Menyediakan fondasi teknis yang dapat ditingkatkan secara horizontal untuk skala **jutaan akun terdaftar**, dengan kapasitas concurrent traffic dibuktikan melalui capacity planning dan load/stress testing sebelum produksi. |

---

## 6. Non-Goals MVP

MVP **tidak** ditujukan untuk menjadi HRIS penuh, ERP, ATS enterprise penuh, atau marketplace premium. Hal berikut berada di luar ruang lingkup MVP:

- mandatory workforce-request dan approval bertingkat sebelum lowongan kampus;
- payroll/penggajian;
- presensi;
- performance management;
- full employee onboarding;
- full contract management atau digital signing;
- psikotes terintegrasi;
- native video interview;
- full career counseling;
- career fair/ticketing;
- AI job recommendation, smart matching, candidate ranking, atau premium placement;
- integrasi dua arah dengan seluruh ATS perusahaan;
- tracer study lengkap;
- WhatsApp notification sampai keputusan fase berikutnya.

---

## 7. Target Users dan Personas

### 7.1 Pengunjung Publik

**Kebutuhan utama:** menemukan lowongan resmi, memahami jalur Karier di Kampus vs Karier untuk Alumni, melihat perusahaan, panduan, serta mendaftar akun.

**JTBD:** "Ketika saya mencari peluang kerja yang berhubungan dengan kampus, saya ingin mengetahui lowongan mana yang resmi dan relevan sehingga saya tidak bergantung pada sumber yang tidak terverifikasi."

### 7.2 Kandidat Eksternal

**Kebutuhan utama:** registrasi, verifikasi email, profil, CV/dokumen privat, pencarian lowongan publik, application, jadwal, offering, status, withdraw.

### 7.3 Mahasiswa Tingkat Akhir

**Kebutuhan utama:** mendaftar secara mandiri, memperoleh eligibility final-year sesuai sumber kampus, dan melamar secara mandiri.

**Prinsip:** tidak ada auto-candidate dan tidak ada auto-apply.

### 7.4 Alumni

**Kebutuhan utama:** verifikasi alumni melalui sumber resmi kampus, profil, dokumen, lowongan alumni, application, outcome.

### 7.5 Company Admin / Company Recruiter

**Kebutuhan utama:** daftar akun, verifikasi email, company profile, legalitas, company verification, anggota perusahaan, vacancy, applicant, schedule, outcome.

### 7.6 Career Center Staff / Manager

**Kebutuhan utama:** company verification, vacancy moderation, partnership, alumni/outcome monitoring, reporting, template/setting moderation sesuai kewenangan.

### 7.7 Admin Kepegawaian / HR-SDM

**Kebutuhan utama:** membuat lowongan kampus, mengelola pelamar, tahapan, jadwal, penilaian, offering, outcome, laporan.

### 7.8 Selector

**Kebutuhan utama:** mengakses dan menilai kandidat hanya pada stage/assignment yang diberikan.

### 7.9 Pimpinan / Auditor

**Kebutuhan utama:** laporan dan audit read-only sesuai kewenangan tanpa mengambil alih tindakan operasional.

### 7.10 Super Admin

**Kebutuhan utama:** user/role, master data, konfigurasi, SMTP, integrasi, audit, retensi, dan keamanan platform.

---

## 8. Product Principles

1. **One portal, two business tracks, one candidate database.**
2. **Trust before publication:** company harus VERIFIED sebelum company vacancy dibuat; company vacancy harus melalui moderasi sebelum tayang.
3. **Verification ≠ Partnership:** `Terverifikasi` dan `Mitra Kampus` adalah dua konsep berbeda.
4. **Candidate autonomy:** mahasiswa tingkat akhir dan alumni tidak otomatis dilamarkan.
5. **Single lifecycle:** satu candidate+vacancy menggunakan satu application lifecycle; reapply membuka kembali record yang sama bila diotorisasi.
6. **Privacy by default:** dokumen kandidat privat; owner vacancy hanya mendapat dokumen yang dipilih untuk application terkait.
7. **Explicit consent:** sharing dokumen/application memiliki consent yang dapat ditelusuri.
8. **History over destructive mutation:** withdrawal, reopening, revision, status change, moderation, offering, dan outcome mempertahankan history.
9. **Role + object ownership:** coarse RBAC tidak cukup; akses harus dibatasi ke object dan scope yang sah.
10. **Action is not always status:** `Submit/Diajukan` adalah action, bukan persisted vacancy status.
11. **Transaction survives notification failure:** kegagalan SMTP tidak membatalkan transaksi bisnis utama; notifikasi menggunakan outbox/retry.
12. **Auditability:** perubahan penting memiliki actor, timestamp, reason/note sesuai konteks, dan audit history.
13. **No hidden product assumptions:** hal yang tidak ditentukan BRD/FSD harus ditutup melalui Product Owner decision, bukan convention agent/developer.

---

## 9. MVP Scope

### 9.1 Identity & Access

- self-registration kandidat dan recruiter;
- normalisasi email case-insensitive;
- email verification melalui one-time link;
- login/logout;
- forgot/reset password;
- account status dan abuse controls;
- multi-role user;
- RBAC + object-level authorization.

### 9.2 Candidate Experience

- profil kandidat;
- status eksternal/final-year/alumni;
- verifikasi alumni melalui sumber kampus yang ditentukan;
- CV digital dan link profesional;
- dokumen kandidat privat;
- saved vacancy;
- application history;
- schedule;
- notification;
- account settings.

### 9.3 Company & Recruiter

- recruiter registration dan email verification;
- company profile;
- legal documents;
- company verification lifecycle;
- company membership;
- partnership status terpisah dari verification;
- verified non-partner tetap dapat membuat vacancy.

### 9.4 Company Vacancy

- create/edit draft;
- requirements dan screening questions;
- version history;
- submit review;
- Career Center moderation;
- revision/reject/approve;
- scheduled publication;
- publish;
- suspend/restore/close;
- automatic expiry;
- company scope dan audit history.

### 9.5 Public Vacancy Discovery

- listing vacancy yang boleh tampil publik;
- detail vacancy;
- filtering dan visibility sesuai audience;
- public/company information sesuai privacy rules;
- hanya lifecycle state yang eligible yang dapat muncul sebagai vacancy aktif.

### 9.6 In-Portal Application

- eligibility check;
- preview data dan dokumen yang akan dibagikan;
- screening answers;
- explicit consent;
- submit application;
- satu lifecycle per candidate+vacancy;
- withdraw tanpa destructive delete;
- reapply melalui reopening application lama bila diotorisasi;
- candidate-facing status.

### 9.7 External Apply

- warning sebelum meninggalkan portal;
- tracking `EXTERNAL_APPLY_STARTED` bila tracking diizinkan;
- event tidak dianggap sebagai application confirmed;
- confirmation/outcome hanya melalui actor/integration yang sah pada fase yang mendukung.

### 9.8 Selection, Schedule, Offering, Outcome

- recruitment stage;
- candidate transition;
- schedule test/interview;
- evaluation untuk campus recruitment;
- offering dengan response deadline;
- accept/reject offering;
- outcome hired/rejected/withdrawn/no-show sesuai flow;
- recruiter outcome reporting;
- reminder outcome incomplete.

### 9.9 Campus Recruitment

- Admin Kepegawaian membuat vacancy kampus;
- tidak ada mandatory multi-level approval chain pada MVP;
- campus vacancy hanya `IN_PORTAL`;
- applicant tracking;
- schedule;
- evaluation;
- offering;
- outcome;
- Time-to-Fill.

### 9.10 Notification, Audit, Reporting

- email dan in-app notification sesuai trigger;
- transactional outbox dan retry;
- audit log;
- reporting dan export berbasis role;
- dashboard per role;
- outcome monitoring;
- partnership reporting;
- candidate funnel;
- Time-to-Fill.

---

## 10. Core User Journeys

### 10.1 Candidate In-Portal Journey

```text
Discover vacancy
→ Register/Login
→ Verify email / eligibility as required
→ Complete profile
→ Select vacancy
→ Check eligibility
→ Select documents
→ Answer screening questions
→ Give explicit consent
→ Submit application
→ Track status/schedule
→ Offering
→ Accept/Reject
→ Outcome
```

### 10.2 Recruiter & Company Onboarding

```text
Recruiter Register
→ Verify Email
→ Create/Complete Company Profile
→ Upload Legal Documents
→ Submit Company Verification
→ Career Center Review
→ REVISION_REQUIRED / REJECTED / VERIFIED
→ if VERIFIED: Create Vacancy
```

### 10.3 Company Vacancy Lifecycle

```text
VERIFIED Company
→ DRAFT
→ Edit Requirements / Screening
→ Submit Review
→ PENDING_REVIEW
   ├─ Request Revision → REVISION_REQUIRED → edit → resubmit
   ├─ Reject → REJECTED
   └─ Approve
      ├─ Future window → SCHEDULED → PUBLISHED
      └─ Active window → PUBLISHED
           ├─ SUSPENDED → Restore → PUBLISHED/CLOSED
           ├─ CLOSED
           └─ EXPIRED after close_at
```

`APPROVED` tetap merupakan bagian vocabulary status yang dibekukan, namun flow company MVP yang telah diklarifikasi tidak menggunakannya sebagai emitted state pada approval normal.

### 10.4 Campus Recruitment Journey

```text
Admin Kepegawaian creates campus vacancy
→ Publish
→ Candidate applies in portal
→ Review
→ Selection/Test/Interview
→ Evaluation
→ Offering
→ Candidate response
→ Outcome
```

### 10.5 External Apply Journey

```text
Candidate opens External ATS vacancy
→ Portal warning
→ Candidate confirms leaving portal
→ EXTERNAL_APPLY_STARTED event (if tracking allowed)
→ External ATS
→ Confirmation/outcome only when later supported by valid source
```

### 10.6 Outcome Monitoring

```text
Recruitment reaches final state
→ Recruiter/Admin records outcome
→ Career Center/HR reporting
→ Missing company outcome produces reminder
→ Missing outcome does NOT block new vacancy creation
```

---

## 11. Product Requirements — High Level

### PR-IDENTITY-01 — Single User Identity

Satu email normalized harus merepresentasikan satu user identity dan dibandingkan case-insensitive.

### PR-IDENTITY-02 — Email Verification

Kandidat/recruiter harus melalui verifikasi email dengan one-time link sebelum mengakses capability yang mensyaratkannya.

### PR-CAND-01 — Manual Candidate Participation

Mahasiswa tingkat akhir dan alumni berpartisipasi melalui account/profile mereka sendiri dan tidak dibuat otomatis sebagai pelamar.

### PR-CAND-02 — Private Candidate Documents

Candidate document library bersifat privat. Kandidat memilih dokumen yang dibagikan per application; akses owner vacancy hanya untuk snapshot dokumen yang dibagikan pada application tersebut.

### PR-COMP-01 — Verified Company Gate

Company harus berstatus `VERIFIED` sebelum company vacancy dapat dibuat.

### PR-COMP-02 — Verification Separate from Partnership

Company non-partner yang VERIFIED tetap dapat membuat lowongan. Badge `Mitra Kampus` hanya untuk partnership aktif.

### PR-COMP-03 — Company Membership Scope

Company actor hanya dapat mengakses company/object tempat ia mempunyai membership aktif sesuai kewenangan.

### PR-VAC-01 — Company Vacancy Moderation

Company vacancy tidak boleh tayang sebelum melewati Career Center moderation.

### PR-VAC-02 — Vacancy Audience

Audience resmi hanya:

- `PUBLIC`;
- `ALUMNI_ONLY`;
- `FINAL_YEAR_AND_ALUMNI`;
- `INTERNAL`.

### PR-VAC-03 — Submission as Action

`Submit/Diajukan` tidak disimpan sebagai vacancy status.

### PR-VAC-04 — Versioned Editing

Perubahan vacancy pada state editable mempertahankan version/history sehingga revision dapat ditelusuri.

### PR-VAC-05 — Publication Window

Vacancy hanya boleh dianggap aktif pada publication window yang sah. Company vacancy yang `PUBLISHED` dan mencapai `close_at` harus menjadi `EXPIRED` melalui scheduler.

### PR-APP-01 — Single Application Lifecycle

Sistem harus menegakkan satu lifecycle application untuk pasangan candidate+vacancy.

### PR-APP-02 — Reapply by Reopen

Jika reapply diizinkan pada vacancy yang sama, application lama dibuka kembali dan history dipertahankan; application kedua tidak dibuat.

### PR-APP-03 — Withdrawal Preserves History

Candidate withdrawal mengubah status tanpa menghapus application/history.

### PR-APP-04 — Explicit Consent

Sebelum in-portal application dikirim, candidate harus memberikan consent yang mengidentifikasi purpose, vacancy, dan receiving party yang sah.

### PR-EXT-01 — External Apply Is Not Confirmed Application

Klik/redirect ke External ATS hanya menghasilkan `EXTERNAL_APPLY_STARTED`, bukan `APPLIED` atau confirmed application.

### PR-SEL-01 — Recruitment Tracking

Authorized vacancy owner dapat mengelola status/stage, schedule, selection, dan outcome dalam batas ownership dan role.

### PR-OFFER-01 — Offering

Sistem harus mendukung offering, response deadline, status, dan kandidat dapat menerima atau menolak.

### PR-OUTCOME-01 — Outcome Reporting

Outcome perusahaan perlu dilengkapi untuk reporting. Outcome yang belum lengkap menghasilkan reminder tetapi tidak memblokir company membuat lowongan baru.

### PR-HR-01 — Linear Campus MVP

Admin Kepegawaian mengelola rekrutmen kampus secara linear pada MVP tanpa mandatory multi-level workforce approval.

### PR-NOTIF-01 — Notification Reliability

Kegagalan SMTP tidak boleh membatalkan business transaction; notification dikirim melalui outbox/retry.

### PR-AUDIT-01 — Auditability

Status dan tindakan penting harus memiliki actor/system actor yang sah, timestamp, dan audit/history sesuai kebutuhan bisnis.

### PR-REPORT-01 — Role-Scoped Reporting

Dashboard dan export hanya menampilkan data yang sesuai role, object scope, privacy, dan purpose.

---

## 12. Product State Rules — Key Baselines

### 12.1 Company Verification

```text
DRAFT
→ PENDING_VERIFICATION
→ REVISION_REQUIRED / VERIFIED / REJECTED

VERIFIED
→ SUSPENDED
→ VERIFIED
```

`REJECTED` dan `SUSPENDED` memiliki makna berbeda.

### 12.2 Company Vacancy

Vocabulary status:

- `DRAFT`
- `PENDING_REVIEW`
- `REVISION_REQUIRED`
- `APPROVED`
- `SCHEDULED`
- `PUBLISHED`
- `REJECTED`
- `CLOSED`
- `EXPIRED`
- `SUSPENDED`

Key rules:

- create menghasilkan `DRAFT`;
- submit merupakan action menuju `PENDING_REVIEW`;
- company vacancy dipublikasikan setelah moderation;
- scheduled vacancy dipublikasikan ketika window mulai aktif;
- manual close menghasilkan `CLOSED`;
- system expiry menghasilkan `EXPIRED` setelah close boundary;
- `published_at` tidak boleh ditulis ulang setelah pertama kali dipublikasikan.

### 12.3 Application

Candidate-facing status utama mencakup:

- `APPLIED`
- `UNDER_REVIEW`
- `SHORTLISTED`
- `ASSESSMENT`
- `INTERVIEW`
- `OFFERED`
- `HIRED`
- `REJECTED`
- `WITHDRAWN`
- `NO_SHOW`

Internal stage dapat lebih detail tanpa harus diekspos sebagai status utama candidate.

---

## 13. Experience & Navigation Baseline

### 13.1 Public

- Beranda
- Cari Lowongan
- Karier di Kampus
- Karier untuk Alumni
- Perusahaan
- Panduan
- Daftarkan Perusahaan
- Masuk / Daftar
- Laporkan Lowongan

### 13.2 Candidate

- Dashboard
- Profil Saya
- CV & Dokumen
- Cari Lowongan
- Lamaran Saya
- Jadwal Seleksi
- Lowongan Tersimpan
- Notifikasi
- Pengaturan Akun

### 13.3 Recruiter

- Dashboard
- Profil Perusahaan
- Status Verifikasi
- Dokumen Legalitas
- Kemitraan
- Lowongan
- Pelamar
- Jadwal Seleksi
- Outcome Rekrutmen
- Anggota Perusahaan
- Notifikasi
- Pengaturan Akun

### 13.4 Career Center

- Dashboard
- Verifikasi Perusahaan
- Moderasi Lowongan
- Data Perusahaan
- Kemitraan
- Alumni & Outcome
- Laporan
- Notifikasi
- Template Email
- Pengaturan Moderasi

### 13.5 Admin Kepegawaian

- Dashboard
- Lowongan Kampus
- Pelamar
- Jadwal Seleksi
- Penilaian
- Offering
- Outcome Rekrutmen
- Laporan
- Notifikasi
- Pengaturan

### 13.6 Super Admin

- Pengguna dan Role
- Master Data
- Unit Organisasi
- Program Studi
- Jenis Lowongan
- Template Workflow
- Konfigurasi SMTP
- Template Notifikasi
- Integrasi
- Audit Log
- Retensi Data
- Pengaturan Sistem

---

## 14. Privacy, Consent, Security, and Trust Requirements

### 14.1 Privacy

- candidate documents private by default;
- storage private, bukan public web path;
- document access harus dimediasi oleh authorization;
- recruiter/Admin hanya melihat dokumen yang dibagikan untuk application terkait;
- private documents tidak boleh terindeks search engine;
- export/download sensitive data harus diaudit.

### 14.2 Consent

Application consent:

- tidak pre-checked;
- eksplisit sebelum submit;
- mengidentifikasi purpose;
- mengidentifikasi receiving company/unit;
- menyimpan version/reference/hash, timestamp, dan receiving party yang benar.

### 14.3 Authorization

- RBAC digabung object-level policy dan query scoping;
- cross-company object access harus ditolak/di-scope;
- candidate hanya melihat application sendiri;
- Career Center memoderasi company vacancy tetapi tidak mengambil keputusan hiring kandidat atas nama company;
- Admin Kepegawaian hanya mengelola campus recruitment;
- selector hanya sesuai assignment;
- auditor read-only;
- Super Admin emergency/moderation capability tidak otomatis menjadi company ownership/authoring capability.

### 14.4 Security Baseline

- password disimpan sebagai adaptive hash;
- one-time token untuk email verification/reset;
- secrets tidak dicatat dalam log;
- critical endpoints memiliki rate limiting yang relevan;
- private file access bersifat temporary/controlled;
- notification menggunakan transactional outbox;
- audit tidak boleh mengandung credentials/secrets.

### 14.5 Scalability, Performance, Reliability & Capacity Baseline

Portal dirancang agar dapat berkembang menuju **jutaan akun terdaftar**. Target tersebut **tidak berarti jutaan pengguna aktif bersamaan**. Kapasitas produksi harus ditentukan dan dibuktikan berdasarkan beban nyata: peak concurrent users, requests per second, volume application, public vacancy traffic, upload/download dokumen, queue throughput, dan pola query database.

Baseline produk/engineering:

- aplikasi Laravel harus dapat dijalankan secara **stateless/horizontally scalable** di beberapa instance di belakang load balancer;
- PostgreSQL tetap menjadi primary transactional database; query high-volume wajib menggunakan indexing yang sesuai, query scoping, pagination/cursor, dan tidak boleh mengandalkan unbounded full-table reads pada jalur kritikal;
- Redis digunakan untuk cache, queue, distributed lock, dan rate limiting sesuai arsitektur yang telah dibekukan;
- dokumen/CV tidak disimpan pada local disk instance produksi; file menggunakan private S3-compatible object storage;
- notification/email diproses asynchronous melalui transactional outbox + queue worker agar request bisnis tidak bergantung pada latency SMTP;
- public vacancy discovery harus dirancang untuk memanfaatkan cache/CDN/reverse-proxy caching bila traffic publik membutuhkannya;
- workload berat atau berulang harus berjalan pada queue/scheduler dan tidak memblokir request interaktif bila tidak diperlukan;
- sistem harus memiliki observability untuk application errors, latency, queue backlog, worker health, database connections, slow query, cache health, scheduler failure, dan storage failure;
- connection pooling/read replica/partitioning boleh diperkenalkan **berdasarkan bukti capacity/load test**, bukan sebagai asumsi awal;
- session storage tetap mengikuti architecture baseline saat ini; perubahan ke penyimpanan lain hanya dilakukan bila load test menunjukkan bottleneck yang nyata;
- seluruh list/report besar harus mempunyai pagination/bounded batch processing;
- sebelum production release, tim wajib memiliki capacity model dan melakukan load/stress test pada skenario kritikal dengan dataset representatif;
- target latency, throughput, availability, concurrency, dan queue delay harus dibekukan sebelum production go-live; PRD ini tidak menginventasikan angka yang belum diberikan institusi.

### 14.6 Capacity & Performance Acceptance Metrics

| Metric | Product Intent | Baseline/Target |
|---|---|---|
| Registered accounts | Produk harus mampu berkembang ke skala besar secara data volume | **Millions-scale design objective** |
| Peak concurrent authenticated users | Menentukan kapasitas Laravel/session/database | TBD melalui forecast + load test |
| Peak public concurrent users | Menentukan CDN/cache/web scaling | TBD |
| Peak requests per second | Menentukan jumlah instance dan DB/cache capacity | TBD |
| Daily active users | Capacity planning & adoption | TBD |
| Applications per day / peak hour | Menentukan write throughput pada application/history/audit | TBD |
| Vacancies created/published per day | Menentukan moderation/scheduler volume | TBD |
| File uploads/downloads per peak hour | Menentukan object storage/bandwidth/worker capacity | TBD |
| Interactive API/Web p95 latency | UX performance target | TBD sebelum go-live |
| Public page p95 latency | Public discovery performance target | TBD sebelum go-live |
| Error rate | Reliability guardrail | TBD sebelum go-live |
| Availability | Service reliability objective | TBD sebelum go-live |
| Queue processing delay | Notification/background processing health | TBD sebelum go-live |
| Database slow-query threshold | Operational database guardrail | TBD sebelum go-live |

**Catatan:** jumlah akun terdaftar tidak boleh digunakan sebagai pengganti concurrency target. Klaim capacity hanya sah setelah diuji dengan workload dan dataset yang representatif.

---

## 15. Success Metrics

PRD menggunakan KPI yang telah ditetapkan BRD. **Target angka belum ditentukan oleh sumber yang ada dan tidak diinventasikan dalam PRD ini.** Target numerik harus ditetapkan oleh pemilik bisnis setelah baseline data tersedia.

| ID | Metric | Definition / Intent | Target |
|---|---|---|---|
| KPI-01 | Persentase lowongan melalui portal resmi | Adoption kanal resmi | TBD oleh institusi |
| KPI-02 | Perusahaan VERIFIED & Mitra Kampus aktif | Employer ecosystem | TBD |
| KPI-03 | Waktu verifikasi company & moderasi vacancy | Operational efficiency | TBD |
| KPI-04 | Alumni aktif → apply → selection → offering → hired | Alumni funnel | TBD |
| KPI-05 | Persentase company melaporkan final outcome | Outcome completeness | TBD |
| KPI-06 | Time-to-Fill campus vacancy | `offer_accepted_at - vacancy_published_at` | TBD |
| KPI-07 | Kandidat dapat melihat status application | Candidate transparency | TBD |
| KPI-08 | Penurunan penggunaan form/spreadsheet terpisah | Process consolidation | TBD |
| KPI-09 | Confirmed external outcome / `EXTERNAL_APPLY_STARTED` | External ATS observability | TBD |

### 15.1 Guardrail Metrics yang Disarankan untuk Diukur

Bagian berikut adalah **measurement recommendation**, bukan requirement baru:

- duplicate application rejection/reopen rate;
- vacancy moderation revision rate;
- company verification rejection/revision rate;
- notification dead-letter rate;
- average time candidate stays in each stage;
- percentage of applications with explicit document sharing consent;
- audit completeness for critical lifecycle events;
- p95/p99 latency pada flow kritikal;
- queue backlog dan oldest-job age;
- database connection saturation dan slow-query rate;
- cache hit ratio untuk public/high-read surfaces;
- file upload/download failure rate;
- error rate selama peak traffic;
- capacity headroom terhadap forecast peak traffic.

Target untuk guardrail metrics tersebut belum dibekukan.

---

## 16. Analytics & Reporting Questions

Produk harus dapat menjawab pertanyaan bisnis seperti:

- berapa vacancy aktif per kategori dan target audience;
- berapa company VERIFIED dan berapa yang merupakan Mitra Kampus;
- berapa lama company verification berlangsung;
- berapa lama vacancy moderation berlangsung;
- berapa kandidat yang melihat, melamar, diproses, diwawancarai, mendapat offering, dan hired;
- berapa banyak alumni terserap;
- berapa company yang belum melaporkan outcome;
- berapa External Apply yang hanya started vs sudah confirmed;
- berapa Time-to-Fill campus recruitment;
- vacancy/company/recruitment mana yang memerlukan tindakan operasional.

---

## 17. Dependencies

### 17.1 Product & Institutional Dependencies

MVP bergantung pada:

1. domain/subdomain Portal Karir;
2. SMTP resmi kampus dan kebijakan pengirim resmi;
3. identitas visual dan template komunikasi;
4. sumber data resmi alumni/final-year untuk eligibility/verifikasi;
5. master unit organisasi kampus;
6. master program studi dan master data terkait;
7. kebijakan company verification dan legal documents;
8. keputusan salary presentation;
9. kebijakan retention institusi dan prosedur delete/anonymization bila diwajibkan;
10. owner operasional dan SOP untuk Career Center, Admin Kepegawaian, dan incident escalation.

### 17.2 Technical Delivery Dependencies

Bagian ini adalah **delivery/architecture baseline**, bukan business requirement BRD/FSD:

- **Backend:** Laravel modular monolith;
- **Frontend:** Inertia.js + Vue 3 + Tailwind CSS + Vite;
- **Transactional database:** PostgreSQL 16+;
- **Cache / queue / distributed locks / rate limiting:** Redis;
- **Session:** PostgreSQL sesuai baseline architecture saat ini, dengan evaluasi ulang hanya jika load test menunjukkan bottleneck;
- **Object storage:** private S3-compatible storage;
- **API:** REST `/api/v1` untuk versioned API surface; authenticated web portal menggunakan Inertia sesuai kontrak;
- **Authentication:** Laravel session guard untuk web; Sanctum untuk versioned API ketika surface tersebut diaktifkan;
- **Notification:** transactional outbox + asynchronous queue worker;
- **Deployment:** stateless/containerized Laravel instances yang dapat di-horizontal scale, queue workers yang dapat ditambah sesuai backlog, dan satu coordinated scheduler;
- **Operations:** backup, monitoring, alerting, log aggregation, database observability, queue health, scheduler health, dan incident response;
- **Scale validation:** capacity model + load/stress test sebelum production claim untuk peak concurrency/throughput.

Technical dependency boleh berkembang melalui ADR/change process tanpa mengubah business requirement selama contract produk tetap terpenuhi.

---

## 18. Product Risks

| Risk | Dampak | Mitigasi Produk |
|---|---|---|
| Verifikasi company terlalu lambat | Company enggan menggunakan portal | Queue visibility, SLA metric, revision reason yang jelas |
| Kandidat tidak percaya privacy dokumen | Adoption application turun | Private-by-default, explicit sharing, consent, audit access |
| Role leakage antar-portal | Data leakage / keputusan tidak sah | RBAC + object policy + query scoping + negative tests |
| Outcome company tidak lengkap | Reporting alumni tidak akurat | Reminder, monitoring queue, tanpa blocking vacancy baru |
| SMTP gagal | User tidak menerima notification | Transactional outbox, retry, dead-letter monitoring |
| Status lifecycle ambigu | Implementation drift | Frozen state contracts + PO decision ledger |
| Public vacancy menampilkan state tidak sah | Trust dan compliance risk | PUBLISHED-only visibility boundary + lifecycle filter |
| Scheduler race | Duplicate state/audit | Row locking, post-lock recheck, idempotent system actions |
| Requirement docs tumpang tindih | Team disagreement | Source hierarchy + traceability + change request discipline |
| Peak traffic melebihi capacity | Latency/error meningkat saat campaign/graduate season | Horizontal scaling, capacity planning, load/stress test, autoscaling/operational runbook sesuai environment |
| Query/report tidak bounded pada data besar | Database saturation | Index review, pagination/cursor, bounded batch, slow-query monitoring |
| Queue backlog pada notification/background job | Notification/processing tertunda | Worker scaling, queue metrics, retry/dead-letter monitoring |
| Public vacancy traffic sangat tinggi | Beban read database berlebihan | Cache/CDN/reverse proxy dan cache invalidation yang terkontrol |
| Upload/download dokumen tinggi | Web instance/bandwidth bottleneck | Private object storage, mediated download, upload limits, storage/bandwidth monitoring |

---

## 19. Assumptions

- institusi akan menetapkan sumber resmi untuk alumni/final-year verification;
- Career Center menjadi business owner company verification dan company vacancy moderation;
- Admin Kepegawaian menjadi business owner campus recruitment MVP;
- company tetap menjadi decision owner untuk candidate selection pada company vacancy;
- SMTP, object storage, queue dan scheduler tersedia di lingkungan produksi;
- legal/privacy policy institusi memungkinkan retention default yang telah ditetapkan, dengan delete/anonymization bila diwajibkan;
- target KPI numerik ditentukan setelah baseline usage tersedia;
- product scale objective mencakup **jutaan akun terdaftar**, tetapi peak concurrency dan throughput belum ditetapkan dan wajib ditentukan melalui traffic forecast serta load test sebelum production go-live.

---

## 20. Open Product Decisions

Open decisions berikut **tidak boleh diasumsikan oleh implementasi**:

| ID | Open Decision | Status |
|---|---|---|
| OP-01 | Sumber teknis final alumni/final-year verification | OPEN |
| D-6 | Dokumen legal minimum per jenis company/organisasi | OPEN |
| OP-03 | Salary presentation: wajib/opsional/visibility per vacancy type | OPEN |
| OP-04 | Recruiter portal memakai domain/subdomain sama atau terpisah | OPEN |
| OP-05 | WhatsApp notification masuk fase mana | OPEN / future |
| OP-06 | Slug regeneration dan detail semantics untuk public vacancy discovery | DEFERRED sampai Public Discovery |

**Catatan O-7:** automatic company-vacancy expiry telah **CLOSED** pada frozen contract. Operasi expiry hanya memproses `PUBLISHED -> EXPIRED` ketika `now >= close_at`; `SCHEDULED`, `SUSPENDED`, `CLOSED`, `EXPIRED`, dan state lain tidak di-auto-expire oleh operasi O-7. Tidak ada open product decision tambahan untuk recovery `SCHEDULED` pada baseline ini.

Keputusan first company admin **tidak lagi open**: first successful company creator telah ditetapkan menjadi active `COMPANY_ADMIN`, minimal satu active Company Admin harus dipertahankan.

---

## 21. Delivery / Milestone Snapshot

Bagian ini adalah **snapshot implementasi**, bukan source of truth requirement.

| Milestone | Status | Freeze Reference |
|---|---|---|
| Identity/Auth Foundation | FINAL FROZEN | milestone frozen sebelumnya |
| Candidate Core | FINAL FROZEN | `candidate-core-v2` |
| Candidate Document Upload | FINAL FROZEN | `candidate-document-upload-v1` |
| Recruiter + Company Onboarding | FINAL FROZEN | `company-onboarding-foundation-v1` → `90e5f40` |
| Company Vacancy Authoring | FINAL FROZEN | `company-vacancy-authoring-v1` → `52527a5` |
| Company Vacancy Moderation | FINAL FROZEN | `company-vacancy-moderation-v1` → `44823f8` |
| Company Vacancy Automatic Expiry | IMPLEMENTED / PENDING FINAL FREEZE | docs `64c505c`, runtime `e649c32`, hardening `479c5ff`; O-7 CLOSED; menunggu independent freeze audit dan pembuatan tag |
| Public Vacancy Discovery | NOT STARTED | pending slug/public semantics |
| Candidate Application end-to-end | NOT STARTED / later phase | requirement baseline exists |
| Campus Vacancy end-to-end | NOT STARTED / later phase | requirement baseline exists |
| Selection / Offering / Outcome full flows | PARTIAL/FUTURE implementation | requirement baseline exists |

### 21.1 Current Regression Snapshot

Latest reported expiry branch validation:

- **379 tests**;
- **2,625 assertions**;
- **0 failed**;
- **0 skipped**.

Angka ini adalah **latest reported engineering snapshot** dari expiry branch, bukan product requirement, capacity claim, atau SLA. Angka harus diverifikasi ulang oleh regression/freeze audit pada HEAD yang akan ditag dan dapat berubah pada commit berikutnya.

---

## 22. Proposed Delivery Sequence from Current State

Urutan berikut adalah delivery recommendation berdasarkan dependency produk yang sudah ada:

1. lakukan independent freeze audit untuk Company Vacancy Automatic Expiry dan, jika `SAFE TO FREEZE`, buat tag `company-vacancy-expiry-v1`;
2. tutup slug/public-discovery semantics;
3. implement Public Vacancy Discovery;
4. implement Candidate Application + Consent + Document Sharing;
5. implement company applicant management / selection progression;
6. implement External Apply full lifecycle sesuai kontrak;
7. implement Campus Vacancy lifecycle;
8. implement Selection, Schedule, Evaluation, Offering, Outcome;
9. implement reporting/dashboard yang bergantung pada lifecycle di atas;
10. lakukan cross-domain integration, security, concurrency, privacy, dan UAT audit;
11. lakukan capacity planning dengan dataset representatif, load/stress test, database/query review, queue/backpressure test, dan observability verification sebelum production release.

Urutan ini tidak mengubah scope BRD/FSD; hanya menyusun dependency delivery dari baseline implementasi saat ini.

---

## 23. Product Definition of Done

Sebuah capability produk dapat dianggap selesai apabila:

- requirement dan acceptance criteria memiliki sumber yang disetujui;
- tidak bertentangan dengan BRD/FSD;
- business ambiguity telah memiliki Product Owner decision;
- UI state normal/empty/loading/error/forbidden tersedia bila relevan;
- client dan server validation tersedia;
- RBAC, object-level authorization, dan negative access diuji;
- lifecycle transition diuji;
- audit event yang diwajibkan tercatat;
- notification/outbox yang relevan diuji;
- critical unit/integration test lulus;
- tidak ada critical/high defect tanpa mitigasi;
- full regression tidak memiliki failure/skip yang tidak diterima;
- list/report/batch dengan volume besar menggunakan pagination atau bounded processing;
- capability high-traffic memiliki observability yang relevan dan tidak memiliki obvious unbounded query pada critical path;
- capability yang masuk capacity-critical production path telah mempunyai acceptance load/performance criteria dan diuji terhadap profile yang disepakati sebelum go-live;
- dokumentasi kontrak diperbarui;
- independent audit menyatakan aman untuk freeze pada milestone yang kritikal;
- change yang menyimpang dari frozen baseline melalui change request/PO approval.

---

## 24. Traceability Matrix

| PRD Area | BRD 1.1 | FSD 1.1 |
|---|---|---|
| Product vision & two-track model | §1–§3 | §1–§2 |
| KPI / success metrics | §4 | Reporting & outcome sections |
| MVP scope / non-goals | §5 | §12–§15 and functional modules |
| Roles & stakeholders | §6 | §2–§4 |
| Recruiter/company onboarding | §7.2, §8.2 | FR-ONB, FR-COMP |
| Company vacancy | §7.3, §8.3 | FR-VAC |
| Candidate/profile/documents | §2.4, §8.4 | FR-CAN |
| Application | §7.4, §8.4 | FR-APP |
| External Apply | §7.5, §8.6 | FR-EXT |
| Selection / offering / outcome | §8.5 | FR-SEL / reporting |
| Campus recruitment | §2.1, §7.1 | FR-HR |
| Notification | §8.7 | FR-NOTIF |
| Audit/reporting/consent | §8.8 and non-functional requirements | FR-AUD / FR-CONSENT / FR-REP / §10 |
| Retention | Decision v1.1 / business NFR | security/retention section |
| Scalability / performance / capacity | Product scale objective + approved technical delivery baseline | Architecture ADRs / NFR implementation; numeric SLOs TBD sebelum go-live |

---

## 25. Change Management

Karena PRD ini dibuat setelah sebagian implementation milestone telah dibekukan:

1. PRD v1.0 tidak boleh digunakan untuk membatalkan frozen behavior yang sesuai BRD/FSD tanpa change request.
2. Requirement baru yang tidak berasal dari BRD/FSD harus memiliki label **Product Proposal** sampai Product Owner menyetujuinya.
3. Setelah disetujui, perubahan harus diturunkan ke BRD/FSD atau decision log/contract sesuai jenis perubahan.
4. Implementasi tidak boleh menggunakan PRD sebagai alasan untuk mengabaikan database invariant, privacy rule, atau authorization contract yang telah dibekukan.
5. Setiap major release sebaiknya memperbarui bagian Delivery Snapshot dan Traceability tanpa mengubah history keputusan lama.
6. Perubahan technical capacity architecture (mis. connection pooling, read replica, partitioning, atau session-store change) harus didorong oleh ADR + evidence dari load/operational data dan tidak boleh dianggap sebagai perubahan business requirement tanpa alasan produk.

---

## 26. Approval Baseline

PRD v1.0-C2 dapat dinyatakan **Product Baseline** setelah final correction verification berikut:

- PRD ↔ BRD v1.1;
- PRD ↔ FSD v1.1;
- PRD ↔ approved Product Owner decisions;
- PRD ↔ frozen milestone behavior;
- daftar open decisions tidak kehilangan item yang masih belum ditentukan.

Hasil audit sebaiknya hanya memperbaiki PRD jika terjadi mismatch framing/traceability. BRD/FSD tidak diubah hanya untuk menyesuaikan PRD tanpa change request.

---

# Appendix A — Product Decision Summary & Source Classification

Bagian ini membedakan aturan yang berasal langsung dari BRD/FSD dengan klarifikasi Product Owner pasca-FSD agar traceability tidak tercampur.

| Decision / Rule | Source Classification |
|---|---|
| Recruiter self-register sebelum company onboarding | BRD/FSD baseline |
| Email verification melalui link | BRD/FSD baseline |
| Company `VERIFIED` sebelum vacancy creation | BRD/FSD baseline |
| Verified non-partner company tetap dapat membuat vacancy | BRD/FSD + approved clarification |
| Partnership terpisah dari verification | BRD/FSD baseline |
| Mahasiswa tingkat akhir manual register/apply | BRD/FSD + approved clarification |
| Vacancy submit adalah action, bukan status | BRD/FSD + frozen contract |
| Reapply membuka application yang sama | Approved Product Owner decision / frozen invariant |
| External ATS click bukan confirmed application | BRD/FSD + approved clarification |
| Outcome incomplete menghasilkan reminder, bukan blocker vacancy baru | Approved Product Owner decision |
| Campus recruitment MVP linear tanpa mandatory approval chain | BRD/FSD + approved Product Owner decision |
| Retention default indefinite dengan exception policy/law | Approved Product Owner decision / security baseline |
| Time-to-Fill berakhir ketika offering diterima | Approved Product Owner decision |
| Audience vacancy resmi hanya empat kategori | BRD/FSD + frozen contract |
| First company creator menjadi active `COMPANY_ADMIN` | Approved Product Owner decision (D-1) |
| VA-1…VA-5 authoring semantics | Approved Product Owner decisions + frozen technical contracts |
| B-1…B-5 moderation/publication semantics | Approved Product Owner decisions + frozen technical contracts |
| O-7 normal `PUBLISHED -> EXPIRED` path | FSD FR-VAC-008 / §8.3 + approved clarification |
| Target skala jutaan akun terdaftar | Product Owner scale objective; concurrency/throughput target TBD dan wajib dibuktikan lewat capacity/load test |
| Stack Laravel + PostgreSQL + Redis + S3-compatible + Inertia/Vue | Approved technical architecture baseline, bukan business rule BRD/FSD |

Open decisions tetap tercatat di §20 dan tidak boleh dianggap closed oleh Appendix ini.

# Appendix B — Terminology

| Term | Meaning |
|---|---|
| Terverifikasi | Company telah lolos verifikasi Career Center. |
| Mitra Kampus | Company memiliki partnership aktif; bukan syarat vacancy creation. |
| Company Admin | Company member dengan kewenangan administratif sesuai contract. |
| Company Recruiter | Company member yang mengelola aktivitas recruiter sesuai contract. |
| Submit / Diajukan | Action mengirim object ke review; bukan vacancy status. |
| PUBLISHED | Vacancy sedang tayang dan menjadi kandidat untuk public discovery. |
| CLOSED | Vacancy ditutup secara eksplisit/manual sesuai lifecycle. |
| EXPIRED | Vacancy melewati close boundary melalui system expiry. |
| External Apply Started | Event redirect/tracking; bukan confirmed application. |
| Application Reopen | Membuka lifecycle application lama untuk reapply tanpa membuat duplicate application. |
| Time-to-Fill | `offer_accepted_at - vacancy_published_at`. |

---

**End of PRD v1.0-C2**
