<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Outcome\Exceptions;

use RuntimeException;

/** The requested outcome does not exist, or exists outside the actor's scope (enumeration-safe). */
final class RecruitmentOutcomeNotFound extends RuntimeException {}
