<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Exceptions;

use RuntimeException;

/** INV-024: IN_PORTAL → EXTERNAL_ATS is refused while applications exist. */
final class VacancyHasApplications extends RuntimeException {}
