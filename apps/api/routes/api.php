<?php

/*
|--------------------------------------------------------------------------
| API Routes — VERSIONED_API surface (/api/v1)
|--------------------------------------------------------------------------
| Sanctum bearer token authenticated, stateless, CSRF-exempt, and carrying an
| explicit backward-compatibility commitment. Inventory: docs/api/API_ENDPOINTS.md
| (57 endpoints). Behaviour: docs/api/API_CONTRACT.md.
|
| BOOTSTRAP PHASE: intentionally empty. The versioned surface is activated in a
| later phase together with Sanctum and its `personal_access_tokens` table
| (DATABASE_SCHEMA.md §23). Declaring routes now, before the Actions and Policies
| they delegate to exist, would create a compatibility promise we cannot keep.
*/
