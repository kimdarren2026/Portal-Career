<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_application_status_histories');
            $table->bigInteger('application_id');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->bigInteger('from_stage_id')->nullable();
            $table->bigInteger('to_stage_id')->nullable();
            $table->string('event_type', 32);
            $table->bigInteger('actor_user_id')->nullable();
            $table->text('reason')->nullable();
            $table->string('candidate_visibility', 16);
            $table->text('candidate_visible_note')->nullable();
            $table->timestampTz('occurred_at');
            $table->foreign('application_id', 'fk_application_status_histories_application_id')
                ->references('id')->on('applications')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('from_stage_id', 'fk_application_status_histories_from_stage_id')
                ->references('id')->on('recruitment_stages')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('to_stage_id', 'fk_application_status_histories_to_stage_id')
                ->references('id')->on('recruitment_stages')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('actor_user_id', 'fk_application_status_histories_actor_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE application_status_histories RENAME CONSTRAINT application_status_histories_pkey TO pk_application_status_histories');
        DB::statement("ALTER TABLE application_status_histories ADD CONSTRAINT chk_application_status_histories_event_type CHECK (event_type IN ('APPLICATION_CREATED', 'STATUS_CHANGED', 'STAGE_CHANGED', 'APPLICATION_REOPENED', 'WITHDRAWN', 'REJECTED', 'OFFER_ACCEPTED', 'NO_SHOW'))");
        DB::statement("ALTER TABLE application_status_histories ADD CONSTRAINT chk_application_status_histories_candidate_visibility CHECK (candidate_visibility IN ('VISIBLE', 'INTERNAL'))");
        DB::statement('CREATE INDEX idx_ash_application_occurred ON application_status_histories (application_id, occurred_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_histories');
    }
};
