<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/**
 * A vacancy-required candidate document type was not shared. Reserved for
 * this milestone: no authoritative source defines a required-document-type
 * field on vacancies today, so this exception has no current trigger path —
 * it exists so the frozen `APPLICATION_DOCUMENT_REQUIRED` code is reachable
 * the moment such a requirement structure is approved, without inventing one now.
 */
final class ApplicationDocumentRequired extends RuntimeException {}
