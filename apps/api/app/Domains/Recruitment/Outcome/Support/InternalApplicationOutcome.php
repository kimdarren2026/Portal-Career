<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Outcome\Support;

/**
 * OC-1 (approved and CLOSED, 27 August 2026) — the exact `outcome` vocabulary
 * for `source_type = INTERNAL_APPLICATION` in Recruitment Outcome
 * Foundation v1. Enforced by request/runtime validation only; the `outcome`
 * column itself carries no database CHECK constraint. Scoped to
 * `INTERNAL_APPLICATION` only — never assumed for a future `EXTERNAL_APPLY`
 * vocabulary.
 */
final class InternalApplicationOutcome
{
    public const ALLOWED = ['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'];
}
