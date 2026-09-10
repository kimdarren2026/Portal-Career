<?php

declare(strict_types=1);

namespace App\Domains\ExternalApply\Exceptions;

use RuntimeException;

final class ExternalApplyEventAlreadyConfirmed extends RuntimeException {}
