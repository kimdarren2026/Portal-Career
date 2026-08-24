<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_outcomes', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_recruitment_outcomes');
            $table->string('source_type', 32);
            $table->bigInteger('application_id')->nullable();
            $table->bigInteger('external_apply_event_id')->nullable();
            $table->string('outcome', 64);
            $table->string('reported_by_source', 32);
            $table->bigInteger('confirmed_by')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('application_id', 'fk_recruitment_outcomes_application_id')
                ->references('id')->on('applications')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('external_apply_event_id', 'fk_recruitment_outcomes_external_apply_event_id')
                ->references('id')->on('external_apply_events')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('confirmed_by', 'fk_recruitment_outcomes_confirmed_by')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE recruitment_outcomes RENAME CONSTRAINT recruitment_outcomes_pkey TO pk_recruitment_outcomes');
        DB::statement("ALTER TABLE recruitment_outcomes ADD CONSTRAINT chk_recruitment_outcomes_source_type CHECK (source_type IN ('INTERNAL_APPLICATION', 'EXTERNAL_APPLY'))");
        DB::statement("ALTER TABLE recruitment_outcomes ADD CONSTRAINT chk_recruitment_outcomes_reported_by_source CHECK (reported_by_source IN ('CANDIDATE', 'COMPANY', 'CAMPUS_STAFF', 'INTEGRATION'))");
        DB::statement("ALTER TABLE recruitment_outcomes ADD CONSTRAINT chk_recruitment_outcomes_source_xor CHECK ((source_type = 'INTERNAL_APPLICATION' AND application_id IS NOT NULL AND external_apply_event_id IS NULL) OR (source_type = 'EXTERNAL_APPLY' AND external_apply_event_id IS NOT NULL AND application_id IS NULL))");
        DB::statement('CREATE UNIQUE INDEX uq_recruitment_outcomes_application ON recruitment_outcomes (application_id) WHERE application_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX uq_recruitment_outcomes_external_event ON recruitment_outcomes (external_apply_event_id) WHERE external_apply_event_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_recruitment_outcomes_created ON recruitment_outcomes (created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_outcomes');
    }
};
