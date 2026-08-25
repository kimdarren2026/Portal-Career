<?php

declare(strict_types=1);

namespace App\Domains\Company\Exceptions;

use RuntimeException;

/** INV-017: one active membership per (company, user). */
final class CompanyMemberAlreadyActive extends RuntimeException {}
