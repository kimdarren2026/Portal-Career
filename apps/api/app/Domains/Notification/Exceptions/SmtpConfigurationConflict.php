<?php

declare(strict_types=1);

namespace App\Domains\Notification\Exceptions;

use RuntimeException;

/**
 * A concurrent writer won the single-active SMTP slot
 * (`uq_smtp_configurations_active`, INV-036) and a retry did not resolve it.
 * Surfaces as `409 CONFLICT` — never a raw SQLSTATE / constraint name.
 */
final class SmtpConfigurationConflict extends RuntimeException {}
