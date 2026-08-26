<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Exceptions;

use RuntimeException;

/** RA-2: the vacancy's current_status is outside {PUBLISHED, CLOSED, EXPIRED} and blocks existing-applicant processing. */
final class VacancyNotProcessable extends RuntimeException {}
