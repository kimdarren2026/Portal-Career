<?php

declare(strict_types=1);

namespace App\Domains\Consent\Support;

use App\Domains\Application\Exceptions\ConsentReceiverMismatch;
use App\Domains\Vacancy\Models\Vacancy;

/**
 * Derives the single consent receiving party from a vacancy's ownership
 * (INV-023) — never accepted from the client. `ownership_type = COMPANY` →
 * `receiving_company_id`; `ownership_type = CAMPUS` → `receiving_organizational_unit_id`.
 * Exactly one, never both, never neither.
 *
 * Foundation v1 supports COMPANY vacancies only in runtime (Campus Vacancy
 * authoring does not exist yet); the CAMPUS branch is implemented so the
 * invariant and its future receiver shape are not foreclosed, but no code
 * path in this milestone can reach it — vacancy eligibility already requires
 * `ownership_type = COMPANY` before this class is ever called.
 *
 * @return array{receiving_company_id: int|null, receiving_organizational_unit_id: int|null}
 */
final class ConsentReceiver
{
    /** @return array{receiving_company_id: int|null, receiving_organizational_unit_id: int|null} */
    public static function forVacancy(Vacancy $vacancy): array
    {
        return match ($vacancy->ownership_type) {
            'COMPANY' => ['receiving_company_id' => (int) $vacancy->company_id, 'receiving_organizational_unit_id' => null],
            'CAMPUS' => ['receiving_company_id' => null, 'receiving_organizational_unit_id' => (int) $vacancy->organizational_unit_id],
            default => throw new ConsentReceiverMismatch(),
        };
    }

    /**
     * A client-supplied receiver is never trusted — any receiver field present
     * in the request payload at all is a mismatch, since the server always
     * derives it independently (rule 7).
     */
    public static function assertClientSuppliedNone(mixed $companyId, mixed $organizationalUnitId): void
    {
        if ($companyId !== null || $organizationalUnitId !== null) {
            throw new ConsentReceiverMismatch();
        }
    }
}
