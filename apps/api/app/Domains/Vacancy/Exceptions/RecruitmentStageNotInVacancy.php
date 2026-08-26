<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Exceptions;

use RuntimeException;

/** INV-019: a submitted stage id does not belong to this vacancy's current stage set. */
final class RecruitmentStageNotInVacancy extends RuntimeException {}
