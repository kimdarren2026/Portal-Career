<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Vacancy\Enums\VacancyType;

/**
 * Read-only reference view of the frozen `VacancyType` vocabulary for the
 * Super Admin "Jenis Lowongan" page (PO decision
 * SUPER_ADMIN_VACANCY_TYPE_REFERENCE_MVP — API_CONTRACT.md Part X item 61).
 *
 * The enum is the single source of truth; there is no table and no repository.
 * Each row carries the raw code and a source-backed ownership classification
 * (INV-018 / `VacancyType::companyAuthorable()`). Display labels are a
 * frontend concern (frozen FE-3 map, Part X item 49) and are not returned
 * here. This class never mutates, orders by business semantics, or invents a
 * fourth type.
 */
final class VacancyTypeReference
{
    /** @return list<array{code: string, ownership: string}> */
    public static function all(): array
    {
        $companyAuthorable = VacancyType::companyAuthorable();

        return array_map(
            static fn (VacancyType $type): array => [
                'code' => $type->value,
                'ownership' => in_array($type->value, $companyAuthorable, true) ? 'COMPANY' : 'CAMPUS',
            ],
            VacancyType::cases(),
        );
    }
}
