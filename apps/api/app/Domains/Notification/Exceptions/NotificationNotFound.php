<?php

declare(strict_types=1);

namespace App\Domains\Notification\Exceptions;

use RuntimeException;

/** The notification does not exist, or belongs to another user (enumeration-safe — answered as a plain 404). */
final class NotificationNotFound extends RuntimeException {}
