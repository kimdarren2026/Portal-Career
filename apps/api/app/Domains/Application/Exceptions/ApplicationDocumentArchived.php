<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/** A selected candidate_documents id is archived and can no longer be shared. */
final class ApplicationDocumentArchived extends RuntimeException {}
