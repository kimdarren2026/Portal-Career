# Canonical Stitch Selection Report

This report evaluates the 29 screen groups marked PENDING_SELECTION.md against the approved BRD/FSD rules, frozen UI baseline, and Stitch design system. Scores use: Business 50, Role/Nav 15, Terminology 15, Design 10, and UX 10.

For HIGH-confidence selections, the chosen code.html and screen.png were copied into the corresponding canonical design/stitch directory. PENDING_SELECTION.md files are retained as reversible audit markers.

**Archive accuracy note (corrected 24 August 2026).** For most groups the source iteration remains present in `archive/stitch-iterations/`. For the seven redesigned groups, however, a subsequent cleanup removed the superseded working-tree iterations: **21 iteration directories and 42 files were removed, and 7 redesigned screens comprising 14 canonical files were introduced.** The removed legacy iterations are therefore no longer individually present in the working-tree iteration archive for those seven groups. **All original export files remain recoverable from the verified raw Stitch ZIP at `archive/packages/stitch_campus_career_portal_system_original.zip`**, whose integrity has been re-verified (229/229 files, `unzip -t` clean). No screen mapping was changed and no file was restored.

## Screen: candidate/cv-dokumen

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 45 | 8 | 12 | 8 | 9 | 82 |
| iteration-2 | 43 | 14 | 12 | 9 | 9 | 87 |
| iteration-3 | 49 | 15 | 14 | 10 | 10 | 98 |

### Recommended Canonical Version

iteration-3

### Reason

Current 2026, Indonesian candidate navigation, clear private-document management, and the most complete document cards.

### Rejected Alternatives

- iteration-1: English/generic candidate navigation.
- iteration-2: 2024 example and partnership-centric wording.

### Confidence

HIGH — promoted to design/stitch/candidate/cv-dokumen/.

## Screen: candidate/daftar-akun-kandidat

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-2 | 36 | 15 | 15 | 10 | 10 | 86 |
| iteration-3 | 50 | 14 | 14 | 9 | 10 | 97 |
| iteration-4 | 37 | 14 | 8 | 9 | 10 | 78 |

### Recommended Canonical Version

iteration-1

### Reason

It supports candidate registration without requiring a campus email and presents distinct, unchecked privacy/terms consents with current 2026 content.

### Rejected Alternatives

- iteration-2: implies campus email is required for automatic verification.
- iteration-3: less consistent generic product branding.
- iteration-4: uses the prohibited Mitra Resmi wording.

### Confidence

HIGH — promoted to design/stitch/candidate/daftar-akun-kandidat/.

## Screen: candidate/dashboard-kandidat

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 48 | 15 | 15 | 10 | 10 | 98 |
| iteration-2 | 44 | 13 | 15 | 9 | 10 | 91 |
| iteration-3 | 38 | 12 | 6 | 2 | 3 | 61 |
| iteration-4 | 40 | 12 | 6 | 2 | 3 | 63 |
| iteration-5 | 40 | 10 | 5 | 9 | 9 | 73 |

### Recommended Canonical Version

iteration-1

### Reason

The only polished current candidate dashboard with Indonesian navigation, relevant application states, and no prohibited partner label.

### Rejected Alternatives

- iteration-2: weaker status/detail presentation.
- iteration-3: deprecated Mitra Resmi wording and broken responsive rendering.
- iteration-4: deprecated Mitra Resmi wording and broken responsive rendering.
- iteration-5: English navigation and deprecated Mitra Resmi wording.

### Confidence

HIGH — promoted to design/stitch/candidate/dashboard-kandidat/.

## Screen: candidate/detail-lamaran

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 45 | 15 | 14 | 10 | 9 | 93 |
| iteration-2 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-3 | 37 | 15 | 6 | 9 | 10 | 77 |
| iteration-4 | 39 | 10 | 6 | 9 | 9 | 73 |

### Recommended Canonical Version

iteration-2

### Reason

It has the correct candidate-facing timeline, withdrawal entry point, current data, and separate Terverifikasi and Mitra Kampus labels.

### Rejected Alternatives

- iteration-1: weaker company verification representation and stale application identifier.
- iteration-3: 2024 content and Mitra Resmi.
- iteration-4: Mitra Resmi and inconsistent English navigation.

### Confidence

HIGH — promoted to design/stitch/candidate/detail-lamaran/.

## Screen: candidate/detail-offering

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 48 | 12 | 14 | 9 | 10 | 93 |
| iteration-2 | 42 | 15 | 6 | 10 | 10 | 83 |
| iteration-3 | 50 | 12 | 15 | 9 | 10 | 96 |
| iteration-4 | 42 | 10 | 6 | 10 | 10 | 78 |

### Recommended Canonical Version

iteration-3

### Reason

It cleanly represents an offering awaiting a candidate decision and avoids deprecated company terminology.

### Rejected Alternatives

- iteration-1: less precise company/context labeling.
- iteration-2: 2024 content, Mitra Resmi, and misleading irreversible-decision copy.
- iteration-4: Mitra Resmi.

### Confidence

HIGH — promoted to design/stitch/candidate/detail-offering/.

## Screen: candidate/jadwal-seleksi

> **RESOLVED — Final Stitch Redesign**
>
> Current canonical path: design/stitch/candidate/jadwal-seleksi/
>
> The legacy iteration assessment below is retained for traceability.

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 49 | 15 | 15 | 2 | 3 | 84 |
| iteration-3 | 44 | 15 | 6 | 9 | 10 | 84 |
| iteration-4 | 49 | 15 | 15 | 9 | 8 | 96 |
| iteration-5 | 49 | 10 | 11 | 9 | 7 | 86 |

### Recommended Canonical Version

iteration-4

### Reason

It is the clearest Indonesian schedule layout and does not introduce prohibited terminology.

### Rejected Alternatives

- iteration-1: materially clipped/broken card rendering.
- iteration-3: 2024 data and Mitra Resmi.
- iteration-5: English navigation and a less complete schedule presentation.

### Confidence

SUPERSEDED — the Final Stitch Redesign is the active canonical reference.

## Screen: candidate/konfirmasi-pengunduran-diri

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-2 | 5 | 15 | 15 | 10 | 10 | 55 |
| iteration-3 | 10 | 15 | 15 | 9 | 9 | 58 |
| iteration-4 | 5 | 14 | 15 | 9 | 9 | 52 |

### Recommended Canonical Version

iteration-1

### Reason

It explicitly changes the application to Mengundurkan Diri and says that the application history remains stored.

### Rejected Alternatives

- iteration-2: says the application is removed from recruiter review.
- iteration-3: presents withdrawal as permanent/irreversible.
- iteration-4: says the application is removed and irreversibly deleted.

### Confidence

HIGH — promoted to design/stitch/candidate/konfirmasi-pengunduran-diri/.

## Screen: candidate/lamar-lowongan-periksa-profil

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 45 | 13 | 15 | 9 | 9 | 91 |
| iteration-2 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-3 | 43 | 12 | 15 | 10 | 9 | 89 |

### Recommended Canonical Version

iteration-2

### Reason

Its four-step sequence correctly leads into screening questions and document/preview stages; historical profile dates are legitimate user history.

### Rejected Alternatives

- iteration-1: three-step flow omits the screening-question state represented in this application flow.
- iteration-3: stale 2024 example and less appropriate public-style navigation.

### Confidence

HIGH — promoted to design/stitch/candidate/lamar-lowongan-periksa-profil/.

## Screen: candidate/lamar-lowongan-pertanyaan-seleksi

> **RESOLVED — Final Stitch Redesign**
>
> Current canonical path: design/stitch/candidate/lamar-lowongan-pertanyaan-seleksi/
>
> The legacy iteration assessment below is retained for traceability.

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 50 | 12 | 15 | 10 | 10 | 97 |
| iteration-2 | 47 | 15 | 15 | 10 | 10 | 97 |
| iteration-3 | 46 | 14 | 15 | 8 | 9 | 92 |

### Recommended Canonical Version

iteration-1

### Reason

It has the most complete 2026 screening-question state and clear four-step application progress.

### Rejected Alternatives

- iteration-2: otherwise strong, but uses an older 2024 example.
- iteration-3: less contextual, older, and visually less complete.

### Confidence

SUPERSEDED — the Final Stitch Redesign is the active canonical reference.

## Screen: candidate/lamar-lowongan-pilih-dokumen

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 39 | 13 | 7 | 9 | 10 | 78 |
| iteration-2 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-3 | 48 | 14 | 14 | 9 | 9 | 94 |

### Recommended Canonical Version

iteration-2

### Reason

It expressly says that only documents selected by the candidate are shared with the company and uses current 2026 document examples.

### Rejected Alternatives

- iteration-1: 2023 document examples and Mitra Resmi.
- iteration-3: older document examples and less explicit selected-document handling.

### Confidence

HIGH — promoted to design/stitch/candidate/lamar-lowongan-pilih-dokumen/.

## Screen: candidate/lamar-lowongan-preview-kirim

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 44 | 14 | 7 | 10 | 10 | 85 |
| iteration-2 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-3 | 44 | 14 | 8 | 10 | 10 | 86 |

### Recommended Canonical Version

iteration-2

### Reason

It gives explicit, unchecked recruitment-purpose sharing consent for the named company before submission.

### Rejected Alternatives

- iteration-1: uses Mitra Resmi.
- iteration-3: uses Mitra Resmi despite current sample content.

### Confidence

HIGH — promoted to design/stitch/candidate/lamar-lowongan-preview-kirim/.

## Screen: candidate/lamaran-berhasil

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-2 | 49 | 14 | 15 | 10 | 9 | 97 |
| iteration-3 | 49 | 14 | 15 | 9 | 9 | 96 |

### Recommended Canonical Version

iteration-1

### Reason

It clearly confirms a successful in-portal application with current data and the correct initial candidate-facing state.

### Rejected Alternatives

- iteration-2: less current/internally consistent application detail.
- iteration-3: less complete navigation and hierarchy.

### Confidence

HIGH — promoted to design/stitch/candidate/lamaran-berhasil/.

## Screen: candidate/lamaran-saya

> **RESOLVED — Final Stitch Redesign**
>
> Current canonical path: design/stitch/candidate/lamaran-saya/
>
> The legacy iteration assessment below is retained for traceability.

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 45 | 15 | 15 | 10 | 5 | 90 |
| iteration-2 | 48 | 15 | 7 | 10 | 10 | 90 |
| iteration-3 | 50 | 11 | 13 | 9 | 8 | 91 |
| iteration-4 | 47 | 14 | 15 | 2 | 3 | 81 |

### Recommended Canonical Version

iteration-3

### Reason

It avoids an incorrect combined company verification/partnership chip and represents valid candidate application states.

### Rejected Alternatives

- iteration-1: incomplete status-filter support.
- iteration-2: conflates Terverifikasi and Mitra Kampus in one company badge.
- iteration-4: materially broken/cropped layout.

### Confidence

SUPERSEDED — the Final Stitch Redesign is the active canonical reference.

## Screen: candidate/lowongan-tersimpan

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 45 | 15 | 15 | 10 | 9 | 94 |
| iteration-2 | 46 | 14 | 15 | 9 | 9 | 93 |
| iteration-3 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-4 | 42 | 10 | 7 | 10 | 9 | 78 |

### Recommended Canonical Version

iteration-3

### Reason

It has current content, a consistent Indonesian candidate layout, Terverifikasi where shown, and a correctly distinguished Karier di Kampus card.

### Rejected Alternatives

- iteration-1: does not surface company verification as clearly.
- iteration-2: less complete verification semantics.
- iteration-4: uses Mitra Resmi.

### Confidence

HIGH — promoted to design/stitch/candidate/lowongan-tersimpan/.

## Screen: candidate/notifikasi

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 42 | 15 | 12 | 6 | 7 | 82 |
| iteration-2 | 44 | 15 | 15 | 10 | 10 | 94 |
| iteration-4 | 50 | 11 | 14 | 10 | 10 | 95 |

### Recommended Canonical Version

iteration-4

### Reason

It carries current 2026 system examples, readable notification states, and no prohibited terminology.

### Rejected Alternatives

- iteration-1: combines company verification/partnership wording and has weak visual rendering.
- iteration-2: otherwise good but uses older 2024 notification data.

### Confidence

HIGH — promoted to design/stitch/candidate/notifikasi/.

## Screen: candidate/offering-diterima

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 50 | 14 | 15 | 7 | 6 | 92 |
| iteration-2 | 49 | 15 | 15 | 10 | 10 | 99 |

### Recommended Canonical Version

iteration-2

### Reason

It is the cleanest current confirmation of accepted offering/Diterima and retains a clear next-step hierarchy.

### Rejected Alternatives

- iteration-1: incomplete company content and visibly cropped controls.

### Confidence

HIGH — promoted to design/stitch/candidate/offering-diterima/.

## Screen: candidate/profil-saya

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 43 | 15 | 13 | 10 | 10 | 91 |
| iteration-2 | 30 | 15 | 5 | 10 | 10 | 70 |
| iteration-3 | 44 | 10 | 8 | 10 | 10 | 82 |
| iteration-4 | 50 | 15 | 15 | 10 | 10 | 100 |

### Recommended Canonical Version

iteration-4

### Reason

It correctly distinguishes Alumni and Alumni Terverifikasi, has current candidate context, and maintains the strongest profile information hierarchy.

### Rejected Alternatives

- iteration-1: generic candidate identity and partner-centric copy.
- iteration-2: uses the invalid Mahasiswa/Fresh Graduate identity and generic Verified label.
- iteration-3: Mitra Resmi and less consistent navigation.

### Confidence

HIGH — promoted to design/stitch/candidate/profil-saya/.

## Screen: candidate/verifikasi-alumni

> **RESOLVED — Final Stitch Redesign**
>
> Current canonical path: design/stitch/candidate/verifikasi-alumni/
>
> The legacy iteration assessment below is retained for traceability.

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 10 | 15 | 8 | 10 | 10 | 53 |
| iteration-2 | 20 | 10 | 10 | 9 | 9 | 58 |

### Final Canonical Resolution

RESOLVED — Final Stitch Redesign

### Reason

Neither version is suitable: each presents alumni verification as a gate to premium/exclusive partner vacancies, which conflicts with the approved candidate eligibility model.

### Rejected Alternatives

- iteration-1: Premium features and Mitra Kampus-exclusive eligibility.
- iteration-2: exclusive partner-vacancy eligibility and generic verification treatment.

### Confidence

SUPERSEDED — the Final Stitch Redesign is the active canonical reference.

## Screen: candidate/verifikasi-email

> **RESOLVED — Final Stitch Redesign**
>
> Current canonical path: design/stitch/candidate/verifikasi-email/
>
> The legacy iteration assessment below is retained for traceability.

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-2 | 50 | 15 | 15 | 9 | 9 | 98 |
| iteration-3 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-4 | 50 | 15 | 15 | 8 | 8 | 96 |

### Recommended Canonical Version

iteration-1

### Reason

It provides the most complete verification help and resend guidance.

### Rejected Alternatives

- iteration-2: less complete support/navigation treatment.
- iteration-3: effectively tied with iteration-1 and has no business/UI discriminator.
- iteration-4: less complete support and hierarchy.

### Confidence

SUPERSEDED — the Final Stitch Redesign is the active canonical reference.

## Screen: candidate/verifikasi-email-dikirim

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-2 | 5 | 15 | 15 | 10 | 10 | 55 |

### Recommended Canonical Version

iteration-1

### Reason

It correctly confirms a candidate email-verification message, including email access and resend guidance.

### Rejected Alternatives

- iteration-2: incorrectly says verification continues company registration, a recruiter flow.

### Confidence

HIGH — promoted to design/stitch/candidate/verifikasi-email-dikirim/.

## Screen: candidate/verifikasi-status-alumni

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 25 | 15 | 10 | 10 | 10 | 70 |
| iteration-2 | 50 | 15 | 15 | 10 | 10 | 100 |

### Recommended Canonical Version

iteration-2

### Reason

It frames verification as validation of graduation data and account status, without inventing premium or exclusive-vacancy benefits.

### Rejected Alternatives

- iteration-1: promises exclusive alumni vacancies and partner-priority benefits.

### Confidence

HIGH — promoted to design/stitch/candidate/verifikasi-status-alumni/.

## Screen: career-center/dashboard-career-center

> **RESOLVED — Final Stitch Redesign**
>
> Current canonical path: design/stitch/career-center/dashboard-career-center/
>
> The legacy iteration assessment below is retained for traceability.

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 25 | 12 | 14 | 9 | 10 | 70 |
| iteration-3 | 25 | 15 | 14 | 9 | 10 | 73 |

### Final Canonical Resolution

RESOLVED — Final Stitch Redesign

### Reason

Both versions display Fresh Graduate as a vacancy target in the moderation queue; this is expressly outside the approved target-audience list.

### Rejected Alternatives

- iteration-1: Fresh Graduate target and less role-specific English navigation.
- iteration-3: Fresh Graduate target despite stronger Indonesian Career Center navigation.

### Confidence

SUPERSEDED — the Final Stitch Redesign is the active canonical reference.

## Screen: career-center/tinjau-lowongan

> **RESOLVED — Final Stitch Redesign**
>
> Current canonical path: design/stitch/career-center/tinjau-lowongan/
>
> The legacy iteration assessment below is retained for traceability.

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 35 | 12 | 7 | 10 | 10 | 74 |
| iteration-2 | 42 | 12 | 8 | 10 | 10 | 82 |

### Final Canonical Resolution

RESOLVED — Final Stitch Redesign

### Reason

The visible iteration-2 layout is the stronger base, but both HTML files retain a Mitra Resmi tooltip/title. Iteration-1 also labels rejection as permanent. The deprecated company term prevents a safe canonical promotion.

### Rejected Alternatives

- iteration-1: Mitra Resmi in HTML and Tolak Permanen action.
- iteration-2: Mitra Resmi remains in the badge title attribute, despite its visible Mitra Kampus label.

### Confidence

SUPERSEDED — the Final Stitch Redesign is the active canonical reference.

## Screen: career-center/tinjau-perusahaan

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 50 | 12 | 15 | 10 | 10 | 97 |
| iteration-2 | 43 | 12 | 15 | 10 | 10 | 90 |

### Recommended Canonical Version

iteration-1

### Reason

It offers the complete approved company decisions: Tolak, Tangguhkan, Minta Perbaikan, and Verifikasi Perusahaan, while keeping verification and suspension distinct.

### Rejected Alternatives

- iteration-2: omits the required Tangguhkan action.

### Confidence

HIGH — promoted to design/stitch/career-center/tinjau-perusahaan/.

## Screen: kepegawaian/laporan

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-2 | 42 | 15 | 15 | 10 | 10 | 92 |

### Recommended Canonical Version

iteration-1

### Reason

It uses distinct Kepegawaian navigation and explicitly defines Time-to-Fill through accepted offering, matching the approved metric.

### Rejected Alternatives

- iteration-2: does not state the Time-to-Fill end condition and compresses accepted/rejected outcome meaning.

### Confidence

HIGH — promoted to design/stitch/kepegawaian/laporan/.

## Screen: public/beranda

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 48 | 15 | 15 | 10 | 10 | 98 |
| iteration-2 | 34 | 15 | 4 | 0 | 5 | 58 |

### Recommended Canonical Version

iteration-1

### Reason

It is the complete rendered public landing page, uses Terverifikasi/Mitra Kampus appropriately, distinguishes Karier di Kampus, and includes current 2026 vacancy examples.

### Rejected Alternatives

- iteration-2: uses Mitra Resmi and has no screen.png in the export, preventing visual validation.

### Confidence

HIGH — promoted to design/stitch/public/beranda/.

## Screen: public/detail-lowongan-external-apply

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 50 | 15 | 15 | 10 | 10 | 100 |
| iteration-2 | 43 | 15 | 5 | 10 | 10 | 83 |

### Recommended Canonical Version

iteration-1

### Reason

It separates Terverifikasi and Mitra Kampus and contains the required confirmation modal: external application is only recorded as started, not as a successful application.

### Rejected Alternatives

- iteration-2: uses Mitra Resmi.

### Confidence

HIGH — promoted to design/stitch/public/detail-lowongan-external-apply/.

## Screen: recruiter/daftar-recruiter

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 48 | 15 | 15 | 10 | 10 | 98 |
| iteration-2 | 25 | 15 | 15 | 10 | 10 | 75 |

### Recommended Canonical Version

iteration-1

### Reason

It ends the first account-creation action at Buat Akun with mandatory terms/privacy consent, allowing the separately exported email-verification state to occur before company profile completion.

### Rejected Alternatives

- iteration-2: directly advances to Profil Perusahaan and bypasses the required email-verification step; it also omits the required consent.

### Confidence

HIGH — promoted to design/stitch/recruiter/daftar-recruiter/.

## Screen: recruiter/revisi-lowongan

### Candidates

| Iteration | Business | Role/Nav | Terminology | Design | UX | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| iteration-1 | 30 | 0 | 10 | 10 | 8 | 58 |
| iteration-2 | 50 | 15 | 15 | 9 | 9 | 98 |

### Recommended Canonical Version

iteration-2

### Reason

It correctly places the Perlu Revisi vacancy state in a recruiter dashboard with recruiter-specific navigation and a clear reviewer note/action.

### Rejected Alternatives

- iteration-1: incorrectly renders the recruiter’s revision view as a Career Center admin moderation console.

### Confidence

HIGH — promoted to design/stitch/recruiter/revisi-lowongan/.
