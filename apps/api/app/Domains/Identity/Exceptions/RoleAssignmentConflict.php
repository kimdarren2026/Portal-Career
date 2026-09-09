<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

/**
 * An active `user_roles` assignment already exists for this `(user, role)`
 * pair. The frozen contract for `POST /admin/users/{user}/roles` maps this to
 * `409 CONFLICT` (INV-025 — at most one active assignment per pair).
 */
final class RoleAssignmentConflict extends RuntimeException {}
