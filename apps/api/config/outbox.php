<?php

/*
| Transactional email-outbox delivery defaults (PGC-V1 / PD-B).
|
| These apply only when there is NO active `smtp_configurations` row; when one
| exists its `max_attempts` / `retry_backoff_seconds` win (FR-NOTIF-003). They
| are operational configuration, not a business rule, and may be tuned without
| a contract change.
*/

return [
    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 5),
    'retry_backoff_seconds' => (int) env('OUTBOX_RETRY_BACKOFF_SECONDS', 300),
];
