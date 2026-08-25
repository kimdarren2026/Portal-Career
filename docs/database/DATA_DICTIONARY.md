# Logical Data Dictionary — Portal Karir Kampus

**Status:** Technology-agnostic logical model. No physical types, database engine, SQL, migration, or ORM behavior is selected here.

## Reading This Dictionary

Only these logical types are used: Identifier, String, Text, Boolean, Integer, Decimal, Date, Timestamp, Enum, Reference, and Structured Data.

| Source/Reason value | Meaning |
| --- | --- |
| BRD/FSD Required | Directly required by BRD/FSD v1.1 or the approved logical-ERD brief. |
| Derived Technical Requirement | Needed to implement an approved function, preserve history, or support a relational lifecycle; it adds no business scope. |
| Optional / Pending Decision | Intentionally flexible because BRD/FSD v1.1 leaves policy or implementation unresolved. |

| Required value | Meaning |
| --- | --- |
| Yes | Logically required on every row at creation. |
| No | Optional in all cases. |
| **Conditional** | Nullable for the entity as a whole, but **mandatory** in the circumstances named in the Key/Relation or Description column. Conditional is not a weaker form of optional: where the FSD makes a field mandatory for a given action or state, the rule is stated as a conditional invariant rather than by forcing every row to carry a value. |

A Reference describes a logical relationship, not a selected foreign-key syntax.

Some current-value fields are marked **derived cache**. They exist for queryability, they are never an independent authority, and an invariant binds each to the append-only record that is authoritative for it. Where a cache and its source disagree, the source is correct and the cache is defective.

**Revision:** 1.1-C3 — controlled logical alignment applied 24 August 2026. This dictionary describes **51 logical entities**: 50 business/derivation entities plus 1 system-configuration entity. **The entity count is unchanged from 1.1-C2**; revision 1.1-C3 adds three lifecycle fields to `company_documents` (`first_submitted_at`, `superseded_at`, `superseded_by_document_id`) so the logical model matches the already-approved API verification-evidence retention rule. **No entity was added and no business scope was introduced.**

Revision 1.1-C2 added `selection_stage_assignments` (FR-HR-006 selector scope, resolving H-1) and `smtp_configurations` (FR-NOTIF-005 runtime SMTP management, resolving ADR-010).

Revision 1.1-C1 removed and deferred `vacancy_types` and added `candidate_organizations`, `candidate_certifications`, and `candidate_links` to implement FR-CAN-003.

## Identity & Authorization

### users

Global login identity; separate from company verification and candidate eligibility.

Business uniqueness belongs to `email_normalized` alone (INV-001). `email` is the original/display representation and carries no uniqueness rule; normalization happens before any uniqueness validation.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Stable user identity. | BRD/FSD Required |
| name | String | Yes |  | Display/legal name for account use. | BRD/FSD Required |
| email | String | Yes | Display value — **not unique** | Original/display email address as supplied. Carries no uniqueness constraint; see INV-001. | BRD/FSD Required |
| email_normalized | String | Yes | **Unique — sole authoritative identity key** | Case-normalized comparison value for account uniqueness. | BRD/FSD Required |
| email_verified_at | Timestamp | No |  | Timestamp after a valid email-link verification. | BRD/FSD Required |
| status | Enum | Yes | Account state | PENDING_EMAIL_VERIFICATION, ACTIVE, SUSPENDED, or DISABLED. | BRD/FSD Required |
| phone | String | No |  | User contact number where supplied. Contact data only; implies no notification channel. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Account creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Last mutable account-profile update. | BRD/FSD Required |
| disabled_at | Timestamp | No |  | Time the account entered DISABLED. | BRD/FSD Required |
| anonymized_at | Timestamp | No | Retention marker | Time an authorized retention process anonymized this identity; distinguishes an anonymized record from a live one. Physical mechanism deferred. | Derived Technical Requirement |

### password_credentials

Current password credential metadata. No temporary-password onboarding record is modeled.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Credential record identity. | BRD/FSD Required |
| user_id | Reference | Yes | users.id | Credential owner. | BRD/FSD Required |
| password_hash | String | Yes | Sensitive | Adaptively hashed password; never plaintext. | BRD/FSD Required |
| password_changed_at | Timestamp | Yes |  | Last password establishment/change time. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Credential record creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Credential metadata update time. | BRD/FSD Required |

### email_verification_tokens

One-time email-verification token metadata. Raw tokens are not retained as required data.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Token record identity. | BRD/FSD Required |
| user_id | Reference | Yes | users.id | User whose email is being verified. | BRD/FSD Required |
| token_hash | String | Yes | Sensitive | Hash of one-time token; never a raw-token requirement. | BRD/FSD Required |
| expires_at | Timestamp | Yes |  | Expiration time. | BRD/FSD Required |
| used_at | Timestamp | No |  | Time a valid token is consumed. | BRD/FSD Required |
| revoked_at | Timestamp | No |  | Time the token is invalidated before use. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Token issuance time. | BRD/FSD Required |

### password_reset_tokens

One-time password-reset token metadata required by FR-AUTH-007. Raw tokens are not retained as required data.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Token record identity. | BRD/FSD Required |
| user_id | Reference | Yes | users.id | User whose password may be reset. | BRD/FSD Required |
| token_hash | String | Yes | Sensitive | Hash of the one-time reset token; never a raw-token requirement. | BRD/FSD Required |
| expires_at | Timestamp | Yes |  | Token expiration time. | BRD/FSD Required |
| used_at | Timestamp | No |  | Time a valid token is consumed. | BRD/FSD Required |
| revoked_at | Timestamp | No |  | Time the token is invalidated before use, including after another successful reset. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Token issuance time. | BRD/FSD Required |

### roles

Approved role catalog. Code values include CANDIDATE_EXTERNAL, CANDIDATE_STUDENT_FINAL_YEAR, CANDIDATE_ALUMNI, COMPANY_ADMIN, COMPANY_RECRUITER, CAREER_CENTER_STAFF, CAREER_CENTER_MANAGER, HR_ADMIN, SELECTOR, AUDITOR, and SUPER_ADMIN.

FSD §3.1 also lists `PUBLIC`. It is deliberately **not** stored as a role: an unauthenticated visitor holds no assignment, and public visibility is governed by `vacancies.target_audience`. Roles govern authorization only; see INV-028.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Role identity. | BRD/FSD Required |
| code | String | Yes | Unique | Stable approved role code. | BRD/FSD Required |
| name | String | Yes |  | Human-readable role name. | Derived Technical Requirement |
| description | Text | No |  | Scope explanation, not a permission implementation. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Role catalog creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Role catalog update time. | Derived Technical Requirement |

### user_roles

User and role assignment **history**. Keyed by a surrogate identifier so that revoked assignments remain stored and the same role can later be re-assigned to the same user.

`(user_id, role_id)` is **not** a primary key. INV-025 permits many historical assignments per pair and at most one active assignment where `revoked_at` is absent. This is what allows a FINAL_YEAR_STUDENT to become an ALUMNI without losing role history.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Assignment record identity. Replaces the previous `(user_id, role_id)` composite key. | Derived Technical Requirement |
| user_id | Reference | Yes | users.id | Assigned user. | BRD/FSD Required |
| role_id | Reference | Yes | roles.id | Assigned role. | BRD/FSD Required |
| assigned_at | Timestamp | Yes |  | Assignment time. Never overwritten by a later revocation. | BRD/FSD Required |
| assigned_by | Reference | No | users.id | User who assigned the role; null for system/bootstrap assignment. | BRD/FSD Required |
| revoked_at | Timestamp | No | Active when absent | Assignment end time. Absent means the assignment is active; at most one active row per user and role. | BRD/FSD Required |
| revoked_by | Reference | No | users.id | User who revoked the assignment; null for system revocation. Justified because FR-AUD-001 requires role changes to be attributable. | Derived Technical Requirement |

## Candidate

### candidate_profiles

One user may own at most one candidate profile. Candidate type can change from student to alumni without a new user/profile, and without losing application history.

`current_candidate_type` is the candidate's **self-declared identity category** only. It grants no access and proves nothing; verified eligibility lives in `candidate_verifications` and authorization lives in `user_roles`. See INV-028.

Work preferences are single-valued profile fields rather than a child entity (decision D-6). Repeatable profile content lives in `candidate_educations`, `candidate_work_experiences`, `candidate_skills`, `candidate_organizations`, `candidate_certifications`, and `candidate_links`.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Candidate-profile identity. | BRD/FSD Required |
| user_id | Reference | Yes | users.id; unique | Profile owner. | BRD/FSD Required |
| headline | String | No |  | Candidate professional headline. | BRD/FSD Required |
| phone | String | No |  | Candidate contact number. | BRD/FSD Required |
| province_geographic_area_id | Reference | No | geographic_areas.id | Normalized domicile province. Applied consistently with companies and vacancies per decision D-2. | BRD/FSD Required |
| city_geographic_area_id | Reference | No | geographic_areas.id | Normalized domicile city. | BRD/FSD Required |
| city | String | No | Free-text fallback | Candidate city as supplied when no master area matches. | BRD/FSD Required |
| province | String | No | Free-text fallback | Candidate province as supplied when no master area matches. | BRD/FSD Required |
| summary | Text | No |  | Candidate profile summary. | BRD/FSD Required |
| current_candidate_type | Enum | Yes | Identity category — not eligibility | EXTERNAL, FINAL_YEAR_STUDENT, or ALUMNI. Self-declared; see INV-028. | BRD/FSD Required |
| preferred_employment_type | Enum | No | Work preference | Preferred employment arrangement; FR-CAN-003 "preferensi kerja". | BRD/FSD Required |
| preferred_workplace_mode | Enum | No | Work preference | Preferred onsite/hybrid/remote arrangement. | BRD/FSD Required |
| preferred_location_note | String | No | Work preference | Candidate-supplied location preference where it does not resolve to a master area. | BRD/FSD Required |
| open_to_opportunities | Boolean | No | Work preference | Whether the candidate currently wishes to be considered. Availability signal only; never a ranking or scoring input. | BRD/FSD Required |
| profile_completed_at | Timestamp | No |  | Time required profile information became complete. | BRD/FSD Required |
| anonymized_at | Timestamp | No | Retention marker | Time an authorized retention process anonymized this profile. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Profile creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Profile update time. | BRD/FSD Required |

### candidate_verifications

Eligibility verification for alumni and final-year students, and the **only** admissible basis for target-audience eligibility (INV-028). Verification source remains deliberately flexible; open question 1 is not answered here.

`status` follows the FR-CAN-004 vocabulary. `REJECTED` was removed during correction because FR-CAN-004 does not define it; an unsuccessful verification is recorded as MISMATCH_MANUAL_REVIEW with a reason. A distinct rejected outcome would require an FSD change request.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Verification record identity. | BRD/FSD Required |
| candidate_profile_id | Reference | Yes | candidate_profiles.id | Candidate under verification. | BRD/FSD Required |
| verification_type | Enum | Yes |  | ALUMNI or FINAL_YEAR_STUDENT. | BRD/FSD Required |
| status | Enum | Yes |  | NOT_VERIFIED, PENDING, VERIFIED, or MISMATCH_MANUAL_REVIEW. | BRD/FSD Required |
| student_number | String | Conditional |  | NIM/student number where the selected source uses it. Conditional because the source is open question 1. | BRD/FSD Required |
| program_study_id | Reference | Conditional | study_programs.id | Study program where applicable. | BRD/FSD Required |
| graduation_year | Integer | Conditional |  | Graduation year where applicable. | BRD/FSD Required |
| source_reference | Reference | No | External/official source | Traceable official data source or evidence reference; no source technology assumed. | BRD/FSD Required |
| verified_at | Timestamp | No |  | Verification completion time. | BRD/FSD Required |
| verified_by | Reference | No | users.id | Authorized reviewer where verification is not fully automated. | BRD/FSD Required |
| rejection_reason | Text | Conditional | Required when status is MISMATCH_MANUAL_REVIEW | Candidate-visible or controlled reason for the mismatch/manual-review outcome. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Request/record creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Latest verification record update. | BRD/FSD Required |

### candidate_documents

Private candidate-owned document metadata. File binary is outside the relational logical model in approved private object storage. Documents are never public.

A document referenced by a non-revoked `application_documents` row must not be hard-deleted; it may only be archived or anonymized by an authorized retention process (INV-032).

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Candidate document identity. | BRD/FSD Required |
| candidate_profile_id | Reference | Yes | candidate_profiles.id | Private document owner. | BRD/FSD Required |
| document_type | Enum | Yes |  | Logical type such as CV, transcript, portfolio, certificate, or other approved document. | BRD/FSD Required |
| display_name | String | Yes |  | Candidate-visible document name. | BRD/FSD Required |
| storage_reference | String | Yes | Private object storage reference | Object-storage key/reference, never document binary. | BRD/FSD Required |
| mime_type | String | Yes |  | Uploaded media type. | BRD/FSD Required |
| size | Integer | Yes |  | Logical file size. | BRD/FSD Required |
| checksum | String | No |  | Integrity/snapshot aid where implementation requires it. | Derived Technical Requirement |
| uploaded_at | Timestamp | Yes |  | Upload completion time. | BRD/FSD Required |
| archived_at | Timestamp | No | Retention marker | Archive/retention treatment time; does not imply public visibility and does not remove existing application shares. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Metadata creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Metadata update time. | BRD/FSD Required |

### candidate_saved_vacancies

Approved Lowongan Tersimpan feature (FSD §4.2 Portal Kandidat). Given a surrogate identifier so it can carry model behaviour and policies without a composite-key rewrite; the composite uniqueness rule is retained.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Saved-vacancy record identity. | Derived Technical Requirement |
| candidate_profile_id | Reference | Yes | candidate_profiles.id; unique with vacancy_id | Candidate saving a vacancy. | BRD/FSD Required |
| vacancy_id | Reference | Yes | vacancies.id; unique with candidate_profile_id | Saved vacancy. | BRD/FSD Required |
| saved_at | Timestamp | Yes |  | Time it was saved. | BRD/FSD Required |

### candidate_educations

Repeatable education history implementing FR-CAN-003 "pendidikan". Multi-valued, so it requires its own rows.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Education record identity. | Derived Technical Requirement |
| candidate_profile_id | Reference | Yes | candidate_profiles.id | Candidate owner. | BRD/FSD Required |
| institution_name | String | Yes |  | Education institution name. | BRD/FSD Required |
| study_program_id | Reference | No | study_programs.id | Normalized study program where known. | BRD/FSD Required |
| study_program_name | String | No | Free-text fallback | Supplied program name when no master match exists. | BRD/FSD Required |
| education_level | Enum | Yes |  | Academic level required for profile/requirement comparison. | BRD/FSD Required |
| start_date | Date | No |  | Education start date. | BRD/FSD Required |
| graduation_date | Date | No |  | Completion/graduation date. | BRD/FSD Required |
| graduation_year | Integer | No |  | Graduation year where only a year is available. | BRD/FSD Required |
| score_summary | String | No |  | Optional GPA/score summary; policy is not assumed. Never a ranking input. | Optional / Pending Decision |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

### candidate_work_experiences

Repeatable work history implementing FR-CAN-003 "pengalaman". Distinct from `candidate_organizations`, which covers non-employment organizational activity.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Experience record identity. | Derived Technical Requirement |
| candidate_profile_id | Reference | Yes | candidate_profiles.id | Candidate owner. | BRD/FSD Required |
| employer_name | String | Yes |  | Employer/organization name. | BRD/FSD Required |
| position_title | String | Yes |  | Role title. | BRD/FSD Required |
| employment_type | Enum | No |  | Employment relationship if supplied. | BRD/FSD Required |
| start_date | Date | No |  | Experience start date. | BRD/FSD Required |
| end_date | Date | No |  | Experience end date; null for ongoing work. | BRD/FSD Required |
| is_current | Boolean | Yes |  | Whether this record is current. | BRD/FSD Required |
| description | Text | No |  | Candidate-supplied responsibilities/summary. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

### candidate_skills

Repeatable skill association implementing FR-CAN-003 "keterampilan". References the adopted `skills` master (decision D-3). Given a surrogate identifier because it carries its own attributes.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Skill association identity. | Derived Technical Requirement |
| candidate_profile_id | Reference | Yes | candidate_profiles.id; unique with skill_id | Candidate owner. | BRD/FSD Required |
| skill_id | Reference | Yes | skills.id; unique with candidate_profile_id | Referenced skill. | BRD/FSD Required |
| proficiency_level | Enum | No |  | Candidate-declared proficiency, if the product later uses it. Never a scoring or ranking input. | Optional / Pending Decision |
| created_at | Timestamp | Yes |  | Association creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Association update time. | Derived Technical Requirement |

### candidate_organizations

Repeatable organizational activity implementing FR-CAN-003 "organisasi" — student organizations, committees, professional bodies, and volunteer roles. Kept separate from `candidate_work_experiences` because FR-CAN-003 lists organisasi and pengalaman as distinct profile sections.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Organization record identity. | Derived Technical Requirement |
| candidate_profile_id | Reference | Yes | candidate_profiles.id | Candidate owner. | BRD/FSD Required |
| organization_name | String | Yes |  | Organization/committee name. | BRD/FSD Required |
| role_title | String | Yes |  | Position or role held. | BRD/FSD Required |
| organization_type | Enum | No |  | Logical classification such as student organization, professional body, or volunteer group. | Derived Technical Requirement |
| start_date | Date | No |  | Activity start date. | BRD/FSD Required |
| end_date | Date | No |  | Activity end date; null for ongoing activity. | BRD/FSD Required |
| is_current | Boolean | Yes |  | Whether the activity is ongoing. | Derived Technical Requirement |
| description | Text | No |  | Candidate-supplied summary of the activity. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

### candidate_certifications

Repeatable certification records implementing FR-CAN-003 "sertifikasi". Structured certification data is distinct from a certificate **file**, which remains a private `candidate_documents` record; `document_id` optionally links the two.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Certification record identity. | Derived Technical Requirement |
| candidate_profile_id | Reference | Yes | candidate_profiles.id | Candidate owner. | BRD/FSD Required |
| certification_name | String | Yes |  | Certification or credential name. | BRD/FSD Required |
| issuer_name | String | Yes |  | Issuing organization. | BRD/FSD Required |
| credential_identifier | String | No |  | Credential/registration number where the issuer provides one. | BRD/FSD Required |
| issued_at | Date | No |  | Issue date. | BRD/FSD Required |
| expires_at | Date | No |  | Expiry date; null when the credential does not expire. | BRD/FSD Required |
| credential_url | String | No |  | Issuer verification link where provided. Reference only; the portal performs no issuer integration. | Derived Technical Requirement |
| document_id | Reference | No | candidate_documents.id | Optional link to the private certificate file. Sharing that file with a vacancy owner still requires an explicit application_documents record. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

### candidate_links

Repeatable professional/portfolio links implementing FR-CAN-003 "link profesional/portfolio" (decision D-5). A typed URL list only — the portal stores no portfolio content, renders no external profile, and performs no fetching or crawling of these links.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Link record identity. | Derived Technical Requirement |
| candidate_profile_id | Reference | Yes | candidate_profiles.id; unique with url | Candidate owner. | BRD/FSD Required |
| link_type | Enum | Yes |  | LINKEDIN, PORTFOLIO, PERSONAL_WEBSITE, PUBLICATION, or OTHER. | Derived Technical Requirement |
| label | String | No |  | Candidate-supplied display label. | Derived Technical Requirement |
| url | String | Yes | Unique with candidate_profile_id | Allowed-protocol URL. Validation rules are an implementation concern. | BRD/FSD Required |
| sort_order | Integer | Yes |  | Candidate presentation order. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

## Company & Partnership

### companies

Company profile, legal identity, and verification state. Verification is deliberately separate from partnership.

`normalized_name` carries **no uniqueness constraint** (INV-034). FR-COMP-001 requires duplicate *detection and review*, not hard rejection; companies with legitimately similar or identical names must remain representable once approved.

Fields marked Conditional below are the FR-ONB-002 Wajib set: optional while the company is DRAFT, mandatory before `verification_status` may reach PENDING_VERIFICATION (INV-030).

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Company identity. | BRD/FSD Required |
| name | String | Yes |  | Official/display company name. | BRD/FSD Required |
| normalized_name | String | Yes | Indexed duplicate-detection signal — **not unique** | Normalized name used to flag potential duplicates for authorized review; final matching policy remains flexible. See INV-034. | BRD/FSD Required |
| organization_type_id | Reference | Conditional | organization_types.id | Organization type. Required before submitting for verification (FR-ONB-002 Wajib). | BRD/FSD Required |
| industry_id | Reference | Conditional | industries.id | Industry classification. Required before submitting for verification (FR-ONB-002 Wajib). | BRD/FSD Required |
| website | String | No |  | Company website. Also a duplicate-detection signal by domain. | BRD/FSD Required |
| official_email | String | Conditional |  | Official company email. Required before submitting for verification. Also a duplicate-detection signal by domain. | BRD/FSD Required |
| official_phone | String | No |  | Official company phone. Also a duplicate-detection signal. | BRD/FSD Required |
| address | Text | Conditional |  | Company address. Required before submitting for verification. | BRD/FSD Required |
| province_geographic_area_id | Reference | Conditional | geographic_areas.id | Province from the geography master. Required before submitting for verification (FR-ONB-002 "Master wilayah"). | BRD/FSD Required |
| city_geographic_area_id | Reference | Conditional | geographic_areas.id | City from the geography master. Required before submitting for verification. | BRD/FSD Required |
| legal_identifier | String | No |  | Legal identifier/NIB where applicable; no NIB-only mandate. Also a duplicate-detection signal. | BRD/FSD Required |
| logo_storage_reference | String | No | Private/public asset policy later | Logo object-storage reference. | BRD/FSD Required |
| verification_status | Enum | Yes | State | DRAFT, PENDING_VERIFICATION, REVISION_REQUIRED, VERIFIED, REJECTED, or SUSPENDED. REJECTED and SUSPENDED are distinct outcomes and never equivalent. | BRD/FSD Required |
| verified_at | Timestamp | No |  | Verification completion time. | BRD/FSD Required |
| suspended_at | Timestamp | No |  | Suspension time when applicable. | BRD/FSD Required |
| created_by | Reference | Yes | users.id | User creating the company profile. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Profile creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Profile update time. | BRD/FSD Required |

### company_members

Active and historic user-company membership. Object-level authorization uses active membership.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Membership identity. | BRD/FSD Required |
| company_id | Reference | Yes | companies.id | Company membership context. | BRD/FSD Required |
| user_id | Reference | Yes | users.id | Member user. | BRD/FSD Required |
| company_role | Enum | Yes |  | COMPANY_ADMIN or COMPANY_RECRUITER logical company role. The first creator is active COMPANY_ADMIN; subsequent roles are explicitly selected with no implicit default. | BRD/FSD / approved D-1 |
| status | Enum | Yes |  | Membership lifecycle state; active membership is required for company-object access. | BRD/FSD Required |
| joined_at | Timestamp | Yes |  | Membership activation time. | BRD/FSD Required |
| invited_by | Reference | No | users.id | Inviter when invitation is used; invitation mechanics remain flexible. | BRD/FSD Required |
| revoked_at | Timestamp | No |  | Membership revocation time. | BRD/FSD Required |

### company_documents

Company legal/supporting-document metadata. File binary is not modeled in the logical relational model.

At least one document is required before a company may be submitted for verification (INV-030). **Which** document types satisfy that condition per organization type remains open question 2 and is deliberately not encoded.

**Verification-evidence retention (INV-038).** A document's removability depends entirely on whether it has ever been part of a submitted verification package. `first_submitted_at` is the discriminator: absent means never submitted and removable under draft-edit rules; present means the record is **verification evidence** and destructive deletion is forbidden. Replacement is a **supersede**, not an overwrite — a new `company_documents` record is created and the superseded row is marked, never edited away.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Company document identity. | BRD/FSD Required |
| company_id | Reference | Yes | companies.id | Company owner. | BRD/FSD Required |
| document_type | Enum | Yes |  | Legal/supporting document classification. Minimum required set per organization type remains open. | BRD/FSD Required |
| document_number | String | No |  | Document number where applicable. | BRD/FSD Required |
| storage_reference | String | Yes | Object storage reference | Private storage reference for the file. | BRD/FSD Required |
| issued_at | Date | No |  | Issue date where applicable. | BRD/FSD Required |
| expires_at | Date | No |  | Expiry date where applicable. | BRD/FSD Required |
| status | Enum | Yes |  | Document review/availability state. | BRD/FSD Required |
| first_submitted_at | Timestamp | No | **Evidence discriminator** | Time this exact document record first became part of a **submitted** company-verification package. **Absent** means it has never been submitted. **Once set it is permanent** and is never cleared, even if the company later returns to DRAFT — the fact that a reviewer once saw this document does not become untrue. Governs INV-038. | Derived Technical Requirement implementing already-approved company-verification evidence retention/supersede behaviour |
| superseded_at | Timestamp | No | Supersede chain | Time this document was replaced by a newer document. **Absent while the document is current.** Setting it never deletes or edits the original row. | Derived Technical Requirement implementing already-approved company-verification evidence retention/supersede behaviour |
| superseded_by_document_id | Reference | Conditional | company_documents.id — self-reference | The newer `company_documents` record that replaced this one. **Required when `superseded_at` is present**, absent otherwise. Must reference a document of the **same company**, must never reference the row itself, and must never form a cycle (INV-038). The predecessor row is retained unchanged and remains readable to authorized verification and audit workflows. | Derived Technical Requirement implementing already-approved company-verification evidence retention/supersede behaviour |
| created_at | Timestamp | Yes |  | Record creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Record update time. | BRD/FSD Required |

### company_verification_reviews

Append-only record of important company review actions. Verification history is fully reconstructable from these rows, including VERIFIED → SUSPENDED → VERIFIED.

Reason fields are **conditionally required** (INV-029): mandatory when `action` is REQUEST_REVISION, REJECT, or SUSPEND, per FR-ONB-004 "Revision/rejection/suspension wajib memiliki reason". Only the reason-category vocabulary remains an open policy matter.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Review identity. | BRD/FSD Required |
| company_id | Reference | Yes | companies.id | Reviewed company. | BRD/FSD Required |
| reviewer_user_id | Reference | Yes | users.id | Authorized Career Center reviewer. | BRD/FSD Required |
| action | Enum | Yes |  | SUBMIT, REQUEST_REVISION, VERIFY, REJECT, SUSPEND, or RESTORE. | BRD/FSD Required |
| from_status | Enum | Yes |  | Company status immediately before review action. | BRD/FSD Required |
| to_status | Enum | Yes |  | Resulting company status. | BRD/FSD Required |
| reason_category | Enum | Conditional | Required when action is REQUEST_REVISION, REJECT, or SUSPEND | Controlled reason category. Category vocabulary is a pending policy decision; presence is required by FR-ONB-004. | BRD/FSD Required |
| recruiter_visible_note | Text | Conditional | Required when action is REQUEST_REVISION, REJECT, or SUSPEND | Note visible to company recruiter. | BRD/FSD Required |
| internal_note | Text | No | Sensitive; never recruiter-visible | Internal review note. | BRD/FSD Required |
| reviewed_at | Timestamp | Yes |  | Action time. | BRD/FSD Required |

## Application & Recruitment

### applications

The single lifecycle record for one candidate and one in-portal vacancy. Reopen reuses this identity.

`UNIQUE(candidate_profile_id, vacancy_id)` is unconditional and covers the full lifecycle, not only active applications (INV-007, decision D-1). An applications row may exist **only** when its vacancy has `application_method = IN_PORTAL` (INV-024).

`current_status`, `current_stage_id`, `reopen_count`, and `last_reopened_at` are **derived caches** of `application_status_histories`, which is authoritative (INV-026).

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Application identity. | BRD/FSD Required |
| application_code | String | Yes | Unique | Stable human/system application reference. | BRD/FSD Required |
| candidate_profile_id | Reference | Yes | candidate_profiles.id; unique with vacancy_id | Applying candidate. | BRD/FSD Required |
| vacancy_id | Reference | Yes | vacancies.id; unique with candidate_profile_id; must be IN_PORTAL | Applied vacancy. | BRD/FSD Required |
| current_status | Enum | Yes | State — **derived cache** | APPLIED, UNDER_REVIEW, SHORTLISTED, ASSESSMENT, INTERVIEW, OFFERED, HIRED, REJECTED, WITHDRAWN, or NO_SHOW. Lifecycle/business state; distinct from recruitment stage. | BRD/FSD Required |
| current_stage_id | Reference | No | recruitment_stages.id — **derived cache** | Active operational stage; must belong to the same vacancy (INV-019). Configurable per vacancy; never a competing lifecycle truth. | BRD/FSD Required |
| first_applied_at | Timestamp | Yes |  | Original application submission time. | BRD/FSD Required |
| last_reopened_at | Timestamp | No | **Derived cache** | Most recent authorized reopen time; equals the latest APPLICATION_REOPENED event time. | BRD/FSD Required |
| reopen_count | Integer | Yes | **Derived cache** | Number of authorized reopen actions; equals the count of APPLICATION_REOPENED events. FSD §6.5 treats it as optional tracking; retained as a queryable cache. | BRD/FSD Required |
| withdrawn_at | Timestamp | No |  | Withdrawal time where status is WITHDRAWN. | BRD/FSD Required |
| withdrawal_reason | Text | No | Optional in all cases | Candidate-provided withdrawal reason. FR-APP-006 states this is optional; it is deliberately excluded from the conditional-reason rule INV-029. | BRD/FSD Required |
| hired_at | Timestamp | No |  | Time status becomes HIRED/Diterima when applicable; corresponds to the single accepted offer (INV-031). | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Lifecycle record creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Current-state update time; not a replacement for history. | BRD/FSD Required |

### application_status_histories

Append-only application state and stage-event history. **Authoritative** for the lifecycle; the current-value fields on `applications` are caches of these rows (INV-026).

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | History event identity. | BRD/FSD Required |
| application_id | Reference | Yes | applications.id | Application lifecycle. | BRD/FSD Required |
| from_status | Enum | No |  | Status immediately before the event; null only for initial creation where appropriate. | BRD/FSD Required |
| to_status | Enum | No |  | Status resulting from the event. | BRD/FSD Required |
| from_stage_id | Reference | No | recruitment_stages.id | Stage before event. | BRD/FSD Required |
| to_stage_id | Reference | No | recruitment_stages.id | Stage after event. | BRD/FSD Required |
| event_type | Enum | Yes |  | APPLICATION_CREATED, STATUS_CHANGED, STAGE_CHANGED, APPLICATION_REOPENED, WITHDRAWN, REJECTED, OFFER_ACCEPTED, or NO_SHOW. Reopen uses the FR-APP-003 name APPLICATION_REOPENED. | BRD/FSD Required |
| actor_user_id | Reference | No | users.id | Human actor; null when system-originated. | BRD/FSD Required |
| reason | Text | No |  | Authorized contextual reason. | BRD/FSD Required |
| candidate_visibility | Enum | Yes |  | Whether this event is visible to the candidate. Implements the FR-APP-005 visibility note requirement. | BRD/FSD Required |
| candidate_visible_note | Text | No |  | Candidate-facing wording for the event where it differs from the internal reason. | BRD/FSD Required |
| occurred_at | Timestamp | Yes |  | Event time. | BRD/FSD Required |

### application_documents

Intentional document sharing for one application. This is not a global candidate-document permission, and it never makes a document public.

Snapshot fields are **required at share time** (INV-032) so that later editing, replacement, archival, or anonymization of the candidate's private source document cannot silently alter historical recruitment evidence.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Application document-share identity. | BRD/FSD Required |
| application_id | Reference | Yes | applications.id | Application receiving the document. | BRD/FSD Required |
| candidate_document_id | Reference | Yes | candidate_documents.id | Candidate document intentionally selected. Must not be hard-deleted while this share is not revoked. | BRD/FSD Required |
| shared_at | Timestamp | Yes |  | Sharing time. | BRD/FSD Required |
| snapshot_name | String | Yes | Evidence snapshot | Document display name as it stood at share time. | BRD/FSD Required |
| snapshot_storage_reference | String | Yes | Evidence snapshot | Immutable/versioned storage reference to the file exactly as shared. | BRD/FSD Required |
| snapshot_checksum | String | No | Evidence snapshot | Snapshot integrity value; recommended where the storage layer can supply one. | Derived Technical Requirement |
| revoked_at | Timestamp | No |  | Authorized sharing revocation time. Withdraws future access only; never deletes this record, removes the snapshot, or retroactively invalidates a completed evaluation. Candidate-initiated revocation is human-decision item H-3. | BRD/FSD Required |

### application_screening_answers

Technical Derivation — response to an approved vacancy screening question.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Answer identity. | Derived Technical Requirement |
| application_id | Reference | Yes | applications.id | Applying candidate lifecycle. | Derived Technical Requirement |
| screening_question_id | Reference | Yes | vacancy_screening_questions.id | Question answered; must belong to application vacancy. | Derived Technical Requirement |
| answer_value_reference | Structured Data | Yes |  | Technology-neutral typed answer/value reference. | Derived Technical Requirement |
| answered_at | Timestamp | Yes |  | Answer capture time. | Derived Technical Requirement |

### recruitment_stages

Configurable, vacancy-specific recruitment stages. They avoid hard-coding interview naming.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Stage identity. | BRD/FSD Required |
| vacancy_id | Reference | Yes | vacancies.id | Vacancy defining stage. | BRD/FSD Required |
| name | String | Yes |  | Internal stage name. | BRD/FSD Required |
| stage_type | Enum | Yes |  | Logical stage classification without a fixed interview taxonomy. | BRD/FSD Required |
| sort_order | Integer | Yes |  | Workflow ordering. | BRD/FSD Required |
| active | Boolean | Yes |  | Whether available for current workflow. | BRD/FSD Required |
| candidate_visible_label | String | No |  | Candidate-facing simplified stage label. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Stage creation time. | BRD/FSD Required |

### selection_stage_assignments

Assignment of a SELECTOR to one vacancy-specific recruitment stage, implementing FR-HR-006: "Admin Kepegawaian menetapkan tahap/data yang dapat diakses. Selector hanya melihat data yang diperlukan untuk penugasannya."

This is the **narrowest structure that expresses the requirement** (decision D-7). It is an assignment to a specific stage, not a permission or ACL model: scope is inherited from the stage's vacancy, so an assignment can never reach an unrelated vacancy or an unassigned stage. Assignment history is preserved by the same surrogate-key + revocation pattern as `user_roles` (INV-025); at most one active assignment may exist per selector and stage (INV-037).

Holding the SELECTOR role authorizes *being assigned*; it grants no access on its own. Access requires an **active** assignment row — INV-037 and INV-028's separation principle applied to selection scope.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Assignment record identity. Not keyed on `(recruitment_stage_id, selector_user_id)`, so a revoked assignment can be re-issued. | Derived Technical Requirement |
| recruitment_stage_id | Reference | Yes | recruitment_stages.id | The vacancy-specific stage the selector is assigned to. The stage determines the vacancy, so no separate vacancy reference is stored. | BRD/FSD Required |
| selector_user_id | Reference | Yes | users.id | Assigned selector. Must hold an active SELECTOR role assignment (INV-037). | BRD/FSD Required |
| assigned_by_user_id | Reference | Yes | users.id | Authorized Admin Kepegawaian who made the assignment. FR-AUD-001 requires the change to be attributable. | BRD/FSD Required |
| assigned_at | Timestamp | Yes |  | Assignment time. Never overwritten by a later revocation. | BRD/FSD Required |
| revoked_at | Timestamp | No | Active when absent | Revocation time. Absent means the assignment is active; at most one active row per stage and selector. | BRD/FSD Required |
| revoked_by_user_id | Reference | No | users.id | User who revoked the assignment; null for system revocation. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

### selection_schedules

Broader logical name for the FSD conceptual entity `interviews`. Supports interview and other selection appointments.

`status` excludes `RESCHEDULED` (INV-027): a rescheduled appointment is still SCHEDULED at its new time. A reschedule updates the schedule values, increments `revision_number`, and appends a `selection_schedule_histories` event.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Selection schedule identity. | BRD/FSD Required |
| application_id | Reference | Yes | applications.id | Candidate application scheduled. | BRD/FSD Required |
| recruitment_stage_id | Reference | Yes | recruitment_stages.id | Stage scheduled; must belong to application vacancy (INV-019). | BRD/FSD Required |
| selection_type | Enum | Yes |  | Approved selection appointment type. | BRD/FSD Required |
| starts_at | Timestamp | Yes |  | Start time. | BRD/FSD Required |
| ends_at | Timestamp | No |  | End time where applicable. | BRD/FSD Required |
| timezone | String | Yes |  | Named/timezone reference for schedule interpretation; a named zone rather than a fixed offset. | BRD/FSD Required |
| method | Enum | Yes |  | Selection delivery method — online or on-site. | BRD/FSD Required |
| location | String | No |  | Physical location when applicable. | BRD/FSD Required |
| meeting_url | String | No |  | Remote meeting URL when applicable. | BRD/FSD Required |
| pic_user_id | Reference | No | users.id | Responsible point of contact. | BRD/FSD Required |
| instructions | Text | No |  | Candidate/operational instructions. | BRD/FSD Required |
| attachment_storage_reference | String | No | Object storage reference | Schedule attachment implementing the FR-SEL-001 "lampiran" field. Reference only, never binary. | BRD/FSD Required |
| status | Enum | Yes |  | SCHEDULED, COMPLETED, CANCELLED, or NO_SHOW. RESCHEDULED is deliberately absent; see INV-027. | BRD/FSD Required |
| revision_number | Integer | Yes |  | Incremented on each reschedule so the current values are traceable to a history event. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Schedule creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Latest schedule update time. | BRD/FSD Required |

### selection_schedule_histories

Append-only schedule change history. Retained because FR-SEL-001 requires change and cancellation to preserve history, and because a separate event log keeps the current appointment trivially queryable while append-only schedule rows would require a "which row is current" discriminator.

`RESCHEDULED` lives here as an event type; it is never a current schedule status (INV-027).

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Schedule-history identity. | Derived Technical Requirement |
| selection_schedule_id | Reference | Yes | selection_schedules.id | Changed schedule. | Derived Technical Requirement |
| event_type | Enum | Yes |  | CREATED, RESCHEDULED, COMPLETED, CANCELLED, NO_SHOW, or approved later event. | Derived Technical Requirement |
| previous_snapshot | Structured Data | No |  | Prior relevant schedule values for a change. | Derived Technical Requirement |
| resulting_revision_number | Integer | No |  | The selection_schedules.revision_number produced by this event, linking current values to the event that set them. | Derived Technical Requirement |
| actor_user_id | Reference | No | users.id | Human actor; null for system event. | Derived Technical Requirement |
| reason | Text | No |  | Reschedule/cancel rationale. | Derived Technical Requirement |
| occurred_at | Timestamp | Yes |  | Event time. | Derived Technical Requirement |

### evaluations

Primarily required for campus recruitment; can also support authorized company workflow without a separate application model. No scoring algorithm, ranking, or psychometric engine is assumed — every score field is optional.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Evaluation identity. | BRD/FSD Required |
| application_id | Reference | Yes | applications.id | Evaluated application. | BRD/FSD Required |
| recruitment_stage_id | Reference | Yes | recruitment_stages.id | Evaluated stage; must belong to application vacancy (INV-019). Named consistently with selection_schedules. | BRD/FSD Required |
| evaluator_user_id | Reference | Yes | users.id | Authorized evaluator. | BRD/FSD Required |
| recommendation | Enum | No |  | Evaluator recommendation value. | BRD/FSD Required |
| comments | Text | No |  | Evaluation comments. | BRD/FSD Required |
| total_score | Decimal | No |  | Aggregate score if a later approved rubric uses one. Never computed automatically into a candidate ranking. | BRD/FSD Required |
| submitted_at | Timestamp | No |  | Submission/finalization time. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Evaluation creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Evaluation update time before finalization. | BRD/FSD Required |

### evaluation_items

Per-criterion evaluation detail without assuming a scoring algorithm. Justified as a child entity because FR-SEL-002 and FR-HR-007 list kriteria/bobot/skor/komentar as a repeating group with a variable number of criteria.

No aggregation, normalization, percentile, ranking, or psychometric behaviour is implied by any field here.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Evaluation item identity. | Derived Technical Requirement |
| evaluation_id | Reference | Yes | evaluations.id | Parent evaluation. | Derived Technical Requirement |
| criterion | String | Yes |  | Evaluated criterion. | BRD/FSD Required |
| weight | Decimal | No |  | Criterion weight if an approved rubric applies. | Optional / Pending Decision |
| score | Decimal | No |  | Criterion score if an approved rubric applies. | Optional / Pending Decision |
| comment | Text | No |  | Criterion-specific comment. | BRD/FSD Required |
| sort_order | Integer | Yes |  | Presentation order within the evaluation form. | Derived Technical Requirement |

### offers

Offering record and candidate response. Accepted time is the authoritative Time-to-Fill endpoint.

At most one offer per application may hold `status = ACCEPTED` (INV-031). No onboarding, start-work, contract-signing, or first-working-day field exists here or anywhere in the model.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Offer identity. | BRD/FSD Required |
| application_id | Reference | Yes | applications.id | Offered candidate application. | BRD/FSD Required |
| offered_by_user_id | Reference | Yes | users.id | Authorized offer issuer. | BRD/FSD Required |
| offered_at | Timestamp | Yes |  | Offer creation/offer event time. | BRD/FSD Required |
| response_deadline | Timestamp | No |  | Candidate response deadline. | BRD/FSD Required |
| note | Text | No |  | Offer note. | BRD/FSD Required |
| rejection_reason | Text | No | Optional | Candidate reason when the offer is declined; FR-SEL-005 states this is optional. | BRD/FSD Required |
| document_reference | String | No |  | Offer document storage/reference where provided. | BRD/FSD Required |
| status | Enum | Yes | At most one ACCEPTED per application | DRAFT, SENT, PENDING_RESPONSE, ACCEPTED, REJECTED, or EXPIRED. | BRD/FSD Required |
| sent_at | Timestamp | No |  | Delivery/sending time. | BRD/FSD Required |
| responded_at | Timestamp | No |  | Candidate response time. | BRD/FSD Required |
| offer_accepted_at | Timestamp | Conditional | Required when status is ACCEPTED | Acceptance time used for Time-to-Fill (INV-013). | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Record creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Latest offer-state update. | BRD/FSD Required |

### recruitment_outcomes

Normalized final-outcome reporting for in-portal and confirmed external workflow. Missing outcome is a reminder condition only and **never** gates vacancy creation (INV-014).

`source_type` selects exactly one valid source reference (INV-022). The entity deliberately stores **no** `vacancy_id` and **no** `candidate_profile_id`: both are reachable through the populated source reference, and duplicating them would create a second authority able to contradict its own parent (decision D-4). Reporting joins through the source reference.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Outcome identity. | Derived Technical Requirement |
| source_type | Enum | Yes | Source discriminator | INTERNAL_APPLICATION or EXTERNAL_APPLY. Determines which source reference must be present. | Derived Technical Requirement |
| application_id | Reference | Conditional | applications.id; unique where present | **Required** when source_type is INTERNAL_APPLICATION; **must be absent** when source_type is EXTERNAL_APPLY. | Derived Technical Requirement |
| external_apply_event_id | Reference | Conditional | external_apply_events.id; unique where present | **Required** when source_type is EXTERNAL_APPLY; **must be absent** when source_type is INTERNAL_APPLICATION. | Derived Technical Requirement |
| outcome | Enum | Yes |  | Normalized final outcome/reporting value. | Derived Technical Requirement |
| reported_by_source | Enum | Yes |  | CANDIDATE, COMPANY, CAMPUS_STAFF, or INTEGRATION. Who reported the outcome — distinct from source_type, which identifies the recruitment path. Recording an outcome implies no two-way ATS integration. | Derived Technical Requirement |
| confirmed_by | Reference | No | users.id | Authorized confirmer where a user exists. | Derived Technical Requirement |
| confirmed_at | Timestamp | No |  | Confirmation time. | Derived Technical Requirement |
| notes | Text | No |  | Outcome notes. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |

## Consent & Document Sharing

### consents

Independently auditable consent. It replaces any insufficient application-level Boolean; a Boolean on `applications` must never be introduced as a substitute (INV-011).

Exactly one receiving party is identifiable for recruitment data-sharing consent, determined by the vacancy's ownership (INV-023). Neither-present and both-present are invalid.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Consent identity. | BRD/FSD Required |
| user_id | Reference | Yes | users.id | Consenting user. | BRD/FSD Required |
| application_id | Reference | No | applications.id | Related in-portal application where applicable. | BRD/FSD Required |
| vacancy_id | Reference | No | vacancies.id | Vacancy context; determines which receiving party applies. | BRD/FSD Required |
| receiving_company_id | Reference | Conditional | companies.id | **Required** for a COMPANY-owned vacancy and must equal that vacancy's company; **must be absent** for a CAMPUS-owned vacancy. | BRD/FSD Required |
| receiving_organizational_unit_id | Reference | Conditional | organizational_units.id | **Required** for a CAMPUS-owned vacancy and must equal that vacancy's unit; **must be absent** for a COMPANY-owned vacancy. | BRD/FSD Required |
| consent_type | Enum | Yes |  | Application sharing, permitted external tracking, or other approved type. | BRD/FSD Required |
| consent_version | String | Yes |  | Consent text/version identifier. | BRD/FSD Required |
| consent_text_hash_reference | String | Yes |  | Immutable consent-text hash/reference. | BRD/FSD Required |
| purpose | Text | Yes |  | Recruitment-purpose explanation. | BRD/FSD Required |
| consented_at | Timestamp | Yes |  | Explicit consent time. Consent is never pre-checked. | BRD/FSD Required |
| revoked_at | Timestamp | No |  | Authorized withdrawal/revocation time where legally meaningful. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Record creation time. | BRD/FSD Required |

## External Apply

### external_apply_events

External ATS tracking event stream, separate from the applications lifecycle. Repeat events for the same candidate and vacancy are legitimate, so no uniqueness rule applies.

`EXTERNAL_APPLY_STARTED` must never create an applications row (INV-012), and a vacancy using EXTERNAL_ATS must never have one at all (INV-024). Confirmation metadata records FR-EXT-003's three legitimate paths and implies no two-way ATS integration.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | External-apply event identity. | BRD/FSD Required |
| candidate_profile_id | Reference | Yes | candidate_profiles.id | Candidate starting the external process. | BRD/FSD Required |
| vacancy_id | Reference | Yes | vacancies.id | External-ATS vacancy. | BRD/FSD Required |
| event_type | Enum | Yes |  | Initial approved value is EXTERNAL_APPLY_STARTED. | BRD/FSD Required |
| destination_url_reference | String | Yes |  | Referenced external application destination. | BRD/FSD Required |
| started_at | Timestamp | Yes |  | Time candidate continues past the warning. | BRD/FSD Required |
| confirmation_status | Enum | Yes | Not an application status | Pending/confirmed outcome state for the external process. Never mapped to applications.current_status. | BRD/FSD Required |
| confirmation_source | Enum | No |  | Candidate, company, or legitimate ATS integration source. | BRD/FSD Required |
| confirmed_at | Timestamp | No |  | Confirmation time. | BRD/FSD Required |
| confirmed_by | Reference | No | users.id | Authorized confirmer where applicable. | BRD/FSD Required |
| consent_id | Reference | No | consents.id | Consent governing tracking where required. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Event record creation time. | BRD/FSD Required |

## Notification & Email

### notifications

In-application notification record. Delivery channels other than in-app and email are out of scope; no WhatsApp or other channel entity exists.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Notification identity. | BRD/FSD Required |
| user_id | Reference | Yes | users.id | Recipient user. | BRD/FSD Required |
| type | Enum | Yes |  | Notification classification. | BRD/FSD Required |
| title | String | Yes |  | Recipient-visible title. | BRD/FSD Required |
| body_reference | Text | No |  | Body content or controlled content reference. | BRD/FSD Required |
| related_object_type | String | No | Stable logical entity name | Related business object type; a logical name, never an implementation class path (INV-033). | BRD/FSD Required |
| related_object_id | Identifier | No | Logical reference | Related business object identity. | BRD/FSD Required |
| read_at | Timestamp | No |  | First/read acknowledgement time. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Notification creation time. | BRD/FSD Required |

### email_outbox

Transactional delivery queue that isolates business data commit from SMTP delivery. The outbox row is written inside the business transaction; delivery is attempted only after that transaction commits (INV-015).

`status` consolidates FR-NOTIF-001's `FAILED → RETRY_SCHEDULED` into the single retryable state FAILED_RETRYABLE, whose scheduled retry time is `next_attempt_at`. No SMTP credential, host, or configuration value is stored here.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Outbox message identity. | BRD/FSD Required |
| recipient | String | Yes |  | Intended recipient address; no SMTP secret. | BRD/FSD Required |
| template_reference | String | Yes |  | Template identifier/reference. | BRD/FSD Required |
| payload_reference | Structured Data | No |  | Controlled template payload/reference. | BRD/FSD Required |
| related_object_type | String | No | Stable logical entity name | Related business object type; a logical name, never an implementation class path (INV-033). | BRD/FSD Required |
| related_object_id | Identifier | No | Logical reference | Related business object identity. | BRD/FSD Required |
| status | Enum | Yes |  | PENDING, PROCESSING, SENT, FAILED_RETRYABLE, or DEAD_LETTER. | BRD/FSD Required |
| attempt_count | Integer | Yes |  | Delivery-attempt count. The maximum is `smtp_configurations.max_attempts`, not a field on this row. **This row never holds an SMTP credential** (INV-015, INV-035). | BRD/FSD Required |
| next_attempt_at | Timestamp | No |  | Scheduled retry time; carries the FR-NOTIF-001 RETRY_SCHEDULED meaning. | BRD/FSD Required |
| last_error_summary | Text | No |  | Safe delivery failure summary; never a password, token, or credential. | BRD/FSD Required |
| sent_at | Timestamp | No |  | Successful send time. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Outbox enqueue time. | BRD/FSD Required |

## Audit

### audit_logs

Append-only logical audit of sensitive security and business actions. Passwords, verification tokens, SMTP secrets, and other credentials are excluded.

Audited actions include those named by FR-AUD-001 and, additionally, a change to `candidate_profiles.current_candidate_type` so that the student-to-alumni transition is attributable.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Audit event identity. | BRD/FSD Required |
| actor_user_id | Reference | No | users.id | Human actor; null for system action. | BRD/FSD Required |
| action | String | Yes |  | Audited action such as auth, role change, candidate-type change, verification, moderation, document access, application transition, reopen, withdrawal, schedule, evaluation, offer, export, or SMTP configuration event. | BRD/FSD Required |
| object_type | String | Yes | Stable logical entity name | Audited business object type; a logical name that must remain interpretable indefinitely, never an implementation class path (INV-033). | BRD/FSD Required |
| object_id | Identifier | No | Logical reference | Audited object identity where applicable. | BRD/FSD Required |
| change_summary | Structured Data | No | Safe summary | Redacted change summary; no credential material. | BRD/FSD Required |
| correlation_id | String | No |  | Cross-operation/request correlation reference. | BRD/FSD Required |
| ip_address | String | No | Policy-permitted metadata | Network address only if policy permits collection (human-decision item H-4). | BRD/FSD Required |
| user_agent_device_metadata | Structured Data | No | Policy-permitted metadata | Device/user-agent metadata only if policy permits collection (human-decision item H-4). | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Audit event time. | BRD/FSD Required |

## System Configuration

System configuration is **not business-domain data**. It carries no recruitment meaning, appears in no reporting derivation, and must never be referenced by a business rule or invariant. It is documented here because it holds a credential and therefore requires explicit governance.

### smtp_configurations

Runtime-managed SMTP delivery configuration, implementing FR-NOTIF-005: Super Admin manages host, port, encryption, username, secret, from, reply-to, timeout, retry policy, and a test-send action.

Environment-only configuration cannot satisfy FR-NOTIF-005 because changing a setting would require a deployment. A single entity holding an application-encrypted secret was chosen over a separate `system_secrets` abstraction (decision D-8): exactly one secret is runtime-managed in this system, so a general secret store would be an indirection layer containing one row.

**The secret is governed by INV-035 and is never stored in plaintext, never returned after save, never written to `email_outbox`, never logged, and never placed in an audit payload.** At most one configuration may be active (INV-036).

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Configuration record identity. | Derived Technical Requirement |
| host | String | Yes |  | SMTP server hostname. | BRD/FSD Required |
| port | Integer | Yes |  | SMTP server port. | BRD/FSD Required |
| encryption_mode | Enum | Yes |  | NONE, STARTTLS, or TLS. | BRD/FSD Required |
| username | String | No |  | SMTP authentication username. Not a secret; may be displayed. | BRD/FSD Required |
| encrypted_password | String | No | **Sensitive — write-only** | SMTP credential, encrypted by the application before persistence with a key held outside the database (INV-035). **Never returned by any read operation, API response, export, log line, or audit payload.** A UI shows only whether a credential is set. Changing it replaces the stored ciphertext outright; there is no partial update and no history of previous values. | BRD/FSD Required |
| from_address | String | Yes |  | Envelope/display sender address. | BRD/FSD Required |
| from_name | String | No |  | Display sender name. | BRD/FSD Required |
| reply_to_address | String | No |  | Reply-to address where it differs from the sender. | BRD/FSD Required |
| timeout_seconds | Integer | No |  | Connection/send timeout. | BRD/FSD Required |
| max_attempts | Integer | Yes |  | Maximum delivery attempts before an `email_outbox` row becomes DEAD_LETTER. Implements FR-NOTIF-003 "max attempt configurable". | BRD/FSD Required |
| retry_backoff_seconds | Integer | Yes |  | Base backoff between delivery attempts; the worker applies exponential growth and jitter on top. | BRD/FSD Required |
| is_active | Boolean | Yes | At most one true | Whether this configuration is the one in use. See INV-036. | Derived Technical Requirement |
| last_tested_at | Timestamp | No |  | Time of the most recent Super Admin test send. | BRD/FSD Required |
| last_test_result | Enum | No |  | NOT_TESTED, SUCCESS, or FAILURE. A failure summary must contain no credential material. | BRD/FSD Required |
| updated_by_user_id | Reference | Yes | users.id | Authorized Super Admin who last changed the configuration. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

## Supporting Master Data

### organizational_units

Campus organizational structure for campus-vacancy ownership/unit context.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Organizational-unit identity. | BRD/FSD Required |
| parent_unit_id | Reference | No | organizational_units.id | Parent unit for hierarchy. | Derived Technical Requirement |
| code | String | Yes | Unique | Institutional unit code. | Derived Technical Requirement |
| name | String | Yes |  | Unit name. | BRD/FSD Required |
| active | Boolean | Yes |  | Whether currently selectable as an owner/unit. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

### study_programs

Study-program reference for candidate verification, education, and vacancy requirements.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Study-program identity. | BRD/FSD Required |
| code | String | Yes | Unique | Institutional study-program code. | Derived Technical Requirement |
| name | String | Yes |  | Study-program name. | BRD/FSD Required |
| organizational_unit_id | Reference | No | organizational_units.id | Owning academic unit where applicable. | Optional / Pending Decision |
| active | Boolean | Yes |  | Whether valid for new references. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

### industries

Optional normalized company-industry catalog.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Industry identity. | Derived Technical Requirement |
| code | String | Yes | Unique | Stable industry code. | Derived Technical Requirement |
| name | String | Yes |  | Industry label. | BRD/FSD Required |
| active | Boolean | Yes |  | Whether selectable. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

### organization_types

Optional normalized organization-type catalog for company profile.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Organization-type identity. | Derived Technical Requirement |
| code | String | Yes | Unique | Stable organization-type code. | Derived Technical Requirement |
| name | String | Yes |  | Organization-type label. | BRD/FSD Required |
| active | Boolean | Yes |  | Whether selectable. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

### skills

Adopted normalized skills catalog (decision D-3). It is referenced as a required value by `candidate_skills` and as an optional typed reference by `vacancy_requirements`, so it is a real master rather than an optional catalog.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Skill identity. | Derived Technical Requirement |
| name | String | Yes |  | Candidate/vacancy skill name. | BRD/FSD Required |
| normalized_name | String | Yes | Unique | Normalized name to limit duplicate catalog entries. | Derived Technical Requirement |
| description | Text | No |  | Skill definition/note. | Derived Technical Requirement |
| active | Boolean | Yes |  | Whether selectable for new references. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

### geographic_areas

Reusable province/city reference, applied consistently to company address, candidate domicile, and vacancy location (decision D-2). Each referencing entity keeps a free-text fallback for values that do not resolve to a master area. No specific government/geography source is assumed.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Geographic-area identity. | Derived Technical Requirement |
| parent_geographic_area_id | Reference | No | geographic_areas.id | Parent area for province/city hierarchy. | Derived Technical Requirement |
| code | String | No | Unique when source provides it | Official/institutional geographic code if available. | Optional / Pending Decision |
| name | String | Yes |  | Area name. | Derived Technical Requirement |
| area_type | Enum | Yes |  | Province, city, or approved future geography level. | Derived Technical Requirement |
| active | Boolean | Yes |  | Whether valid for current references. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Record creation time. | Derived Technical Requirement |
| updated_at | Timestamp | Yes |  | Record update time. | Derived Technical Requirement |

## Company & Partnership — continued

### partnerships

Separate campus-company partnership record. Active partnership derives Mitra Kampus; it does not replace verification.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Partnership identity. | BRD/FSD Required |
| company_id | Reference | Yes | companies.id | Partner company. | BRD/FSD Required |
| partnership_type | Enum | Yes |  | Approved partnership classification. | BRD/FSD Required |
| agreement_number | String | No |  | Agreement reference/number. | BRD/FSD Required |
| start_date | Date | Yes |  | Partnership start date. | BRD/FSD Required |
| end_date | Date | No |  | Partnership end date where applicable. | BRD/FSD Required |
| status | Enum | Yes |  | Partnership lifecycle, including ACTIVE where applicable. | BRD/FSD Required |
| campus_pic | String | No |  | Campus point of contact. | BRD/FSD Required |
| company_pic | String | No |  | Company point of contact. | BRD/FSD Required |
| document_reference | String | No |  | Agreement document storage/reference. | BRD/FSD Required |
| notes | Text | No |  | Partnership notes. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Record creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Record update time. | BRD/FSD Required |

## Vacancy

### vacancies

Central aggregate for CAMPUS_EMPLOYMENT, COMPANY_EMPLOYMENT, and INTERNSHIP. One aggregate is retained because all three share the same downstream recruitment graph — applications, stages, schedules, evaluations, offers — and splitting them would fork the application pipeline.

Ownership is an explicit discriminator plus two mutually exclusive typed references (INV-018): exactly one of `company_id` and `organizational_unit_id` is populated on every row. `vacancy_type` is a fixed logical enumeration for MVP; the deferred `vacancy_types` master is not referenced.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Vacancy identity. | BRD/FSD Required |
| vacancy_code | String | Yes | Unique | Stable vacancy reference. | BRD/FSD Required |
| slug | String | Yes | Unique | Public route/readable identifier. | BRD/FSD Required |
| vacancy_type | Enum | Yes |  | CAMPUS_EMPLOYMENT, COMPANY_EMPLOYMENT, or INTERNSHIP. Fixed enumeration for MVP; configurable types are deferred. | BRD/FSD Required |
| ownership_type | Enum | Yes | Owner discriminator | COMPANY or CAMPUS. Determines which owner reference is required and which is forbidden. | BRD/FSD Required |
| company_id | Reference | Conditional | companies.id | **Required** when ownership_type is COMPANY; **must be absent** when ownership_type is CAMPUS. Company must be VERIFIED (INV-002). | BRD/FSD Required |
| organizational_unit_id | Reference | Conditional | organizational_units.id | **Required** when ownership_type is CAMPUS — FR-HR-002 lists unit/fakultas/bagian among minimum campus fields; **must be absent** when ownership_type is COMPANY. | BRD/FSD Required |
| created_by | Reference | Yes | users.id | Authorized creator. | BRD/FSD Required |
| title | String | Yes |  | Vacancy title. | BRD/FSD Required |
| description | Text | Yes |  | Vacancy description. | BRD/FSD Required |
| responsibilities | Text | No |  | Role responsibilities. | BRD/FSD Required |
| employment_type | Enum | Yes |  | Employment arrangement. | BRD/FSD Required |
| workplace_mode | Enum | No |  | Onsite, hybrid, remote, or later approved logical values. | BRD/FSD Required |
| province_geographic_area_id | Reference | No | geographic_areas.id | Normalized vacancy province, applied consistently with companies and candidates (decision D-2). | BRD/FSD Required |
| city_geographic_area_id | Reference | No | geographic_areas.id | Normalized vacancy city. | BRD/FSD Required |
| location | String | No | Free-text fallback | Vacancy location text when it does not resolve to a master area. | BRD/FSD Required |
| openings_count | Integer | Yes |  | Number of openings. May exceed one, which permits several accepted offers across different applications. | BRD/FSD Required |
| minimum_education | String | No |  | Minimum education summary for display; structured requirements live in vacancy_requirements. | BRD/FSD Required |
| experience_requirement | String | No |  | Experience summary for display; structured requirements live in vacancy_requirements. | BRD/FSD Required |
| salary_min | Decimal | No |  | Minimum salary when policy permits display/storage. | Optional / Pending Decision |
| salary_max | Decimal | No |  | Maximum salary when policy permits display/storage. | Optional / Pending Decision |
| salary_currency | String | No |  | Salary currency where salary is recorded. | Optional / Pending Decision |
| target_audience | Enum | Yes |  | PUBLIC, ALUMNI_ONLY, FINAL_YEAR_AND_ALUMNI, or INTERNAL only. Eligibility is evaluated against candidate_verifications (INV-028). | BRD/FSD Required |
| application_method | Enum | Yes |  | IN_PORTAL or EXTERNAL_ATS. Campus is IN_PORTAL only (INV-005). EXTERNAL_ATS forbids any applications row (INV-024). | BRD/FSD Required |
| external_ats_url | String | Conditional | Required when application_method is EXTERNAL_ATS | Allowed-protocol external application URL. | BRD/FSD Required |
| open_at | Timestamp | Conditional | Required before leaving DRAFT | Opening/publication eligibility time. | BRD/FSD Required |
| close_at | Timestamp | Conditional | Required before leaving DRAFT | Closing time; must follow open_at. | BRD/FSD Required |
| published_at | Timestamp | No | Time-to-Fill start point | Actual publication time. Time-to-Fill is undefined while this is null (INV-013). | BRD/FSD Required |
| current_status | Enum | Yes | State | Company set: DRAFT, PENDING_REVIEW, REVISION_REQUIRED, APPROVED, SCHEDULED, PUBLISHED, REJECTED, CLOSED, EXPIRED, SUSPENDED. Campus set excludes the moderation-only values (INV-018). SUBMITTED/DIAJUKAN are never stored (INV-004). | BRD/FSD Required |
| closed_at | Timestamp | No |  | Manual close time. | BRD/FSD Required |
| suspended_at | Timestamp | No |  | Suspension time where applicable. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Vacancy creation time. | BRD/FSD Required |
| updated_at | Timestamp | Yes |  | Latest vacancy update time. | BRD/FSD Required |

### vacancy_versions

Append-only modification/version trail for an existing vacancy. **Retained as a full logical snapshot per version**: FR-VAC-007 requires the version before and after a revision to be stored, which moderation history alone cannot supply because moderation records decisions, not content.

The snapshot is a technology-neutral structured value. No vendor-specific document or JSON implementation is selected.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Version identity. | BRD/FSD Required |
| vacancy_id | Reference | Yes | vacancies.id | Versioned vacancy. | BRD/FSD Required |
| version_number | Integer | Yes | Unique within vacancy | Ordered revision number. | BRD/FSD Required |
| snapshot | Structured Data | Yes | Full logical snapshot | Technology-neutral structured snapshot of the complete vacancy revision, sufficient to reconstruct the before/after state required by FR-VAC-007. | BRD/FSD Required |
| created_by | Reference | Yes | users.id | Actor creating revision. | BRD/FSD Required |
| created_at | Timestamp | Yes |  | Version creation time. | BRD/FSD Required |
| change_reason | Text | No |  | Reason for revision or moderation-driven correction. | BRD/FSD Required |

### vacancy_requirements

Structured qualification requirement rows. One table with a `requirement_type` discriminator — subtype tables are deliberately avoided — but the qualifying value is **typed rather than opaque**.

The previous single `value_reference` structured field was replaced: FR-VAC-003 and FR-HR-002 name "program studi" and "keterampilan" as first-class vacancy fields, and an opaque value cannot answer "which vacancies require study program X", cannot guarantee the reference resolves to a real master row, and would push all requirement semantics into application code.

Exactly one qualifying value is populated per row, selected by `requirement_type`. Not every requirement type is normalized; CERTIFICATION and OTHER_QUALIFICATION intentionally remain free text rather than being over-normalized into further masters.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Requirement identity. | BRD/FSD Required |
| vacancy_id | Reference | Yes | vacancies.id | Vacancy owner. | BRD/FSD Required |
| requirement_type | Enum | Yes | Value discriminator | EDUCATION, STUDY_PROGRAM, EXPERIENCE, SKILL, CERTIFICATION, or OTHER_QUALIFICATION. | BRD/FSD Required |
| education_level | Enum | Conditional | Required when requirement_type is EDUCATION | Minimum academic level, comparable with candidate_educations.education_level. | BRD/FSD Required |
| study_program_id | Reference | Conditional | study_programs.id; required when requirement_type is STUDY_PROGRAM | Qualifying study program from the master. | BRD/FSD Required |
| skill_id | Reference | Conditional | skills.id; required when requirement_type is SKILL | Qualifying skill from the adopted skills master. | BRD/FSD Required |
| minimum_years_experience | Integer | Conditional | Required when requirement_type is EXPERIENCE | Minimum years of relevant experience. | BRD/FSD Required |
| value_text | String | Conditional | Required when requirement_type is CERTIFICATION or OTHER_QUALIFICATION | Free-text qualifying value for requirement types that are deliberately not normalized. | BRD/FSD Required |
| note | Text | No |  | Additional clarification for any requirement type. | Derived Technical Requirement |
| required | Boolean | Yes |  | Whether the requirement is mandatory. | BRD/FSD Required |
| sort_order | Integer | Yes |  | Display/evaluation order. | BRD/FSD Required |

### vacancy_documents

Employer/vacancy supporting documents. These are distinct from private candidate and application-shared documents.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Vacancy document identity. | BRD/FSD Required |
| vacancy_id | Reference | Yes | vacancies.id | Vacancy owner. | BRD/FSD Required |
| document_type | Enum | Yes |  | Supporting-document type. | BRD/FSD Required |
| display_name | String | Yes |  | Document display name. | Derived Technical Requirement |
| storage_reference | String | Yes | Object storage reference | File storage reference, not binary content. | BRD/FSD Required |
| mime_type | String | No |  | File media type. | Derived Technical Requirement |
| size | Integer | No |  | File size. | Derived Technical Requirement |
| uploaded_by | Reference | Yes | users.id | Authorized uploader. | Derived Technical Requirement |
| archived_at | Timestamp | No |  | Retention/archive time when applicable. | Derived Technical Requirement |
| created_at | Timestamp | Yes |  | Metadata creation time. | Derived Technical Requirement |

### vacancy_screening_questions

Technical Derivation — approved screening-question flow.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Question identity. | Derived Technical Requirement |
| vacancy_id | Reference | Yes | vacancies.id | Vacancy that asks the question. | Derived Technical Requirement |
| question_text | Text | Yes |  | Candidate-visible question. | Derived Technical Requirement |
| question_type | Enum | Yes |  | SHORT_TEXT, LONG_TEXT, YES_NO, SINGLE_CHOICE, or NUMBER. | Derived Technical Requirement |
| required | Boolean | Yes |  | Whether an answer is needed to submit. | Derived Technical Requirement |
| options_definition | Structured Data | No |  | Option list only for applicable choice questions. | Derived Technical Requirement |
| sort_order | Integer | Yes |  | Candidate presentation order. | Derived Technical Requirement |
| active | Boolean | Yes |  | Whether the question applies to new applications. | Derived Technical Requirement |

### vacancy_moderation_reviews

Append-only Career Center moderation history for company vacancies. Campus vacancies are not moderated and therefore have no rows here.

Reason fields are **conditionally required** (INV-029): mandatory when `action` is REQUEST_REVISION, REJECT, or SUSPEND, per FR-VAC-006 "Revision/rejection harus memiliki kategori dan recruiter-visible note". Only the reason-category vocabulary remains an open policy matter.

| Field | Logical Type | Required | Key/Relation | Description | Source/Reason |
| --- | --- | --- | --- | --- | --- |
| id | Identifier | Yes | Primary key | Moderation review identity. | BRD/FSD Required |
| vacancy_id | Reference | Yes | vacancies.id | Reviewed vacancy. | BRD/FSD Required |
| reviewer_user_id | Reference | Yes | users.id | Authorized Career Center reviewer. | BRD/FSD Required |
| action | Enum | Yes |  | SUBMIT, REQUEST_REVISION, APPROVE, REJECT, SUSPEND, RESTORE, or applicable CLOSE. | BRD/FSD Required |
| from_status | Enum | Yes |  | Status before action. | BRD/FSD Required |
| to_status | Enum | Yes |  | Status after action. | BRD/FSD Required |
| reason_category | Enum | Conditional | Required when action is REQUEST_REVISION, REJECT, or SUSPEND | Controlled policy reason. Category vocabulary is a pending policy decision; presence is required by FR-VAC-006. | BRD/FSD Required |
| recruiter_visible_note | Text | Conditional | Required when action is REQUEST_REVISION, REJECT, or SUSPEND | Recruiter-visible revision/rejection note. | BRD/FSD Required |
| internal_note | Text | No | Sensitive; never recruiter-visible | Internal moderation note. | BRD/FSD Required |
| reviewed_at | Timestamp | Yes |  | Action time. | BRD/FSD Required |
