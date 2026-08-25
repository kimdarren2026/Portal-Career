<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Enums;

/**
 * Requirement vocabulary and its typed-value mapping, both frozen by
 * `chk_vacancy_requirements_requirement_type` and
 * `chk_vacancy_requirements_typed_value`.
 */
enum RequirementType: string
{
    case Education = 'EDUCATION';
    case StudyProgram = 'STUDY_PROGRAM';
    case Experience = 'EXPERIENCE';
    case Skill = 'SKILL';
    case Certification = 'CERTIFICATION';
    case OtherQualification = 'OTHER_QUALIFICATION';

    /** The single column this type must populate. */
    public function valueColumn(): string
    {
        return match ($this) {
            self::Education => 'education_level',
            self::StudyProgram => 'study_program_id',
            self::Skill => 'skill_id',
            self::Experience => 'minimum_years_experience',
            self::Certification, self::OtherQualification => 'value_text',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
