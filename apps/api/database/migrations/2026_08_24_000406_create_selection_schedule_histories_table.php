<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selection_schedule_histories', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_selection_schedule_histories');
            $table->bigInteger('selection_schedule_id');
            $table->string('event_type', 32);
            $table->jsonb('previous_snapshot')->nullable();
            $table->integer('resulting_revision_number')->nullable();
            $table->bigInteger('actor_user_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('occurred_at');
            $table->foreign('selection_schedule_id', 'fk_selection_schedule_histories_selection_schedule_id')
                ->references('id')->on('selection_schedules')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('actor_user_id', 'fk_selection_schedule_histories_actor_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE selection_schedule_histories RENAME CONSTRAINT selection_schedule_histories_pkey TO pk_selection_schedule_histories');
        DB::statement("ALTER TABLE selection_schedule_histories ADD CONSTRAINT chk_selection_schedule_histories_event_type CHECK (event_type IN ('CREATED', 'RESCHEDULED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'))");
        DB::statement('CREATE INDEX idx_ssh_schedule_occurred ON selection_schedule_histories (selection_schedule_id, occurred_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('selection_schedule_histories');
    }
};
