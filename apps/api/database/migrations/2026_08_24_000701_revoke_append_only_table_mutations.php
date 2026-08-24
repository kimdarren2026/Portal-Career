<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The runtime role is provisioned by the database administrator. It is
     * intentionally distinct from the schema/migration owner, because a table
     * owner cannot be restricted by REVOKE. No credential is defined here.
     */
    private const APPLICATION_ROLE = 'portal_karir_app';

    private const APPEND_ONLY_TABLES = [
        'application_status_histories',
        'company_verification_reviews',
        'vacancy_moderation_reviews',
        'selection_schedule_histories',
        'vacancy_versions',
        'audit_logs',
    ];

    private const APPEND_ONLY_SEQUENCES = [
        'application_status_histories_id_seq',
        'company_verification_reviews_id_seq',
        'vacancy_moderation_reviews_id_seq',
        'selection_schedule_histories_id_seq',
        'vacancy_versions_id_seq',
        'audit_logs_id_seq',
    ];

    public function up(): void
    {
        $tables = implode(', ', self::APPEND_ONLY_TABLES);
        $sequences = implode(', ', self::APPEND_ONLY_SEQUENCES);

        // The application must be able to read and append evidence, but never
        // alter or remove it. The role is deliberately not a table owner.
        DB::statement(sprintf('GRANT SELECT, INSERT ON TABLE %s TO %s', $tables, self::APPLICATION_ROLE));
        DB::statement(sprintf('GRANT USAGE, SELECT ON SEQUENCE %s TO %s', $sequences, self::APPLICATION_ROLE));
        DB::statement(sprintf('REVOKE UPDATE, DELETE ON TABLE %s FROM %s', $tables, self::APPLICATION_ROLE));
    }

    public function down(): void
    {
        $tables = implode(', ', self::APPEND_ONLY_TABLES);
        $sequences = implode(', ', self::APPEND_ONLY_SEQUENCES);

        // Roles are deployment-managed cluster principals and outlive one
        // schema release. Rollback only removes this release's table grants.
        DB::statement(sprintf('REVOKE SELECT, INSERT, UPDATE, DELETE ON TABLE %s FROM %s', $tables, self::APPLICATION_ROLE));
        DB::statement(sprintf('REVOKE USAGE, SELECT ON SEQUENCE %s FROM %s', $sequences, self::APPLICATION_ROLE));
    }
};
