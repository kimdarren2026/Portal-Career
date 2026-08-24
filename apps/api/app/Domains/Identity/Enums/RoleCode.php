<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

/**
 * The approved role catalogue (FSD §3.1). Eleven persisted codes.
 *
 * PUBLIC is deliberately NOT here and is never persisted: an unauthenticated
 * visitor holds no assignment, and public visibility is governed by
 * vacancies.target_audience.
 */
enum RoleCode: string
{
    case CandidateExternal = 'CANDIDATE_EXTERNAL';
    case CandidateStudentFinalYear = 'CANDIDATE_STUDENT_FINAL_YEAR';
    case CandidateAlumni = 'CANDIDATE_ALUMNI';
    case CompanyAdmin = 'COMPANY_ADMIN';
    case CompanyRecruiter = 'COMPANY_RECRUITER';
    case CareerCenterStaff = 'CAREER_CENTER_STAFF';
    case CareerCenterManager = 'CAREER_CENTER_MANAGER';
    case HrAdmin = 'HR_ADMIN';
    case Selector = 'SELECTOR';
    case Auditor = 'AUDITOR';
    case SuperAdmin = 'SUPER_ADMIN';

    /**
     * Holding SELECTOR authorises *being assigned* to a stage; it grants no
     * candidate access on its own (INV-037). Any caller reaching for this enum
     * to answer "may this selector see these candidates?" is asking the wrong
     * question — that requires an active selection_stage_assignments row plus
     * a Policy and an ownership-scoped query.
     */
    public function grantsObjectAccessAlone(): bool
    {
        return false;
    }
}
