<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selection_stage_assignments', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_selection_stage_assignments');
            $table->bigInteger('recruitment_stage_id');
            $table->bigInteger('selector_user_id');
            $table->bigInteger('assigned_by_user_id');
            $table->timestampTz('assigned_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->bigInteger('revoked_by_user_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('recruitment_stage_id', 'fk_selection_stage_assignments_recruitment_stage_id')
                ->references('id')->on('recruitment_stages')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('selector_user_id', 'fk_selection_stage_assignments_selector_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('assigned_by_user_id', 'fk_selection_stage_assignments_assigned_by_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('revoked_by_user_id', 'fk_selection_stage_assignments_revoked_by_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE selection_stage_assignments RENAME CONSTRAINT selection_stage_assignments_pkey TO pk_selection_stage_assignments');
        DB::statement('CREATE UNIQUE INDEX uq_selection_stage_assignments_stage_selector_active ON selection_stage_assignments (recruitment_stage_id, selector_user_id) WHERE revoked_at IS NULL');
        DB::statement('CREATE INDEX idx_ssa_selector_active ON selection_stage_assignments (selector_user_id, recruitment_stage_id) WHERE revoked_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('selection_stage_assignments');
    }
};
