<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/** INV-024: an application row may never exist for an EXTERNAL_ATS vacancy. */
final class ApplicationNotInPortalVacancy extends RuntimeException {}
