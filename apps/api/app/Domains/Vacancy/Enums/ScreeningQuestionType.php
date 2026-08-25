<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Enums;

enum ScreeningQuestionType: string
{
    case ShortText = 'SHORT_TEXT';
    case LongText = 'LONG_TEXT';
    case YesNo = 'YES_NO';
    case SingleChoice = 'SINGLE_CHOICE';
    case Number = 'NUMBER';

    /** `options_definition` is required for SINGLE_CHOICE and rejected otherwise. */
    public function requiresOptions(): bool
    {
        return $this === self::SingleChoice;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
