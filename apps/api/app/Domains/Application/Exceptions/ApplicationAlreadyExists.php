<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/**
 * INV-007: `UNIQUE(candidate_profile_id, vacancy_id)` is unconditional and
 * covers the full lifecycle, not only active applications. Carries the
 * existing application id so the UI can route to *Lihat Status Lamaran*
 * (FR-APP-002).
 */
final class ApplicationAlreadyExists extends RuntimeException
{
    public function __construct(public readonly int $applicationId)
    {
        parent::__construct();
    }
}
