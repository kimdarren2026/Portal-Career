<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The initial Laravel migration predates the frozen index naming scheme.
        // Retain its existing index and align only its identifier with INDEX_STRATEGY.md.
        DB::statement('ALTER INDEX sessions_last_activity_index RENAME TO idx_sessions_last_activity');
    }

    public function down(): void
    {
        DB::statement('ALTER INDEX idx_sessions_last_activity RENAME TO sessions_last_activity_index');
    }
};
