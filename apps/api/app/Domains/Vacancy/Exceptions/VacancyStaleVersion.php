<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Exceptions;

use RuntimeException;

final class VacancyStaleVersion extends RuntimeException {}
