<?php

declare(strict_types=1);

namespace App\Domains\Notification\Exceptions;

use RuntimeException;

final class EmailOutboxMessageNotFound extends RuntimeException {}
