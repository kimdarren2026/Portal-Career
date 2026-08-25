<?php

declare(strict_types=1);

namespace App\Domains\Company\Exceptions;

use RuntimeException;

/** Out of COMPANY_SCOPE, or absent. Both answer 404 — never 403 (matrix §1). */
final class CompanyNotFound extends RuntimeException {}
