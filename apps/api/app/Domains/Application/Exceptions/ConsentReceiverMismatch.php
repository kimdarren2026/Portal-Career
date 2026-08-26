<?php

declare(strict_types=1);

namespace App\Domains\Application\Exceptions;

use RuntimeException;

/** INV-023: the receiver is always server-derived; a client-supplied value is rejected. */
final class ConsentReceiverMismatch extends RuntimeException {}
