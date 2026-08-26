<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Exceptions;

use RuntimeException;

/**
 * A slug did not resolve within the public visibility predicate — whether
 * because no row has that slug, or because a row exists but fails any one
 * condition (status, date window, audience, ownership, company verification).
 * Both cases return the identical 404 VACANCY_NOT_PUBLIC (API_CONTRACT.md
 * §10): a 403 or a differently-shaped 404 would confirm existence to an
 * anonymous caller who must not learn it.
 */
final class VacancyNotPublic extends RuntimeException {}
