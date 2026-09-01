<?php

declare(strict_types=1);

namespace App\Domains\Notification\Exceptions;

use RuntimeException;

/**
 * A test send was requested but no SMTP configuration exists to test.
 * Surfaces as `503 SERVICE_UNAVAILABLE` per the frozen contract
 * (`POST /admin/smtp-configuration/test`).
 */
final class SmtpConfigurationMissing extends RuntimeException {}
