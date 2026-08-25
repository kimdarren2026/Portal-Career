<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Exceptions;

use RuntimeException;

final class ScreeningQuestionInUse extends RuntimeException {}
