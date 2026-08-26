<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/**
 * The vacancy is not currently applicable: not `PUBLISHED`, outside its
 * `open_at`…`close_at` window, or — in this milestone — not `COMPANY`-owned
 * (Campus Vacancy runtime does not exist yet, so a CAMPUS row is treated as
 * not-yet-applicable through this endpoint rather than inventing a distinct
 * error code for a state that cannot occur through any built authoring path).
 */
final class VacancyNotOpenForApplication extends RuntimeException {}
