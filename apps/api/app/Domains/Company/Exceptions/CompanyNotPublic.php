<?php

declare(strict_types=1);

namespace App\Domains\Company\Exceptions;

use RuntimeException;

/**
 * A slug did not resolve within the public company visibility predicate —
 * whether because no row has that slug, or because the company is not
 * currently VERIFIED. Both cases return the identical 404, mirroring
 * VacancyNotPublic: a 403 or a differently-shaped 404 would confirm the
 * company's existence/status to an anonymous caller who must not learn it.
 */
final class CompanyNotPublic extends RuntimeException {}
