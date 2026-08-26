<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

final class ApplicationStaleVersion extends RuntimeException {}
