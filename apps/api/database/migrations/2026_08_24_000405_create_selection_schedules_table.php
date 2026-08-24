<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selection_schedules', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_selection_schedules');
            $table->bigInteger('application_id');
            $table->bigInteger('recruitment_stage_id');
            $table->string('selection_type', 64);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone', 64);
            $table->string('method', 64);
            $table->string('location', 255)->nullable();
            $table->string('meeting_url', 2048)->nullable();
            $table->bigInteger('pic_user_id')->nullable();
            $table->text('instructions')->nullable();
            $table->string('attachment_storage_reference', 512)->nullable();
            $table->string('status', 32);
            $table->integer('revision_number');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('application_id', 'fk_selection_schedules_application_id')
                ->references('id')->on('applications')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('recruitment_stage_id', 'fk_selection_schedules_recruitment_stage_id')
                ->references('id')->on('recruitment_stages')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('pic_user_id', 'fk_selection_schedules_pic_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE selection_schedules RENAME CONSTRAINT selection_schedules_pkey TO pk_selection_schedules');
        DB::statement("ALTER TABLE selection_schedules ADD CONSTRAINT chk_selection_schedules_status CHECK (status IN ('SCHEDULED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'))");
        DB::statement('ALTER TABLE selection_schedules ADD CONSTRAINT chk_selection_schedules_time CHECK (ends_at IS NULL OR ends_at > starts_at)');
        DB::statement('ALTER TABLE selection_schedules ADD CONSTRAINT chk_selection_schedules_revision CHECK (revision_number >= 0)');
        DB::statement('CREATE INDEX idx_selection_schedules_application_id ON selection_schedules (application_id)');
        DB::statement('CREATE INDEX idx_selection_schedules_app_starts ON selection_schedules (application_id, starts_at)');
        DB::statement("CREATE INDEX idx_selection_schedules_upcoming ON selection_schedules (starts_at) WHERE status = 'SCHEDULED'");
    }

    public function down(): void
    {
        Schema::dropIfExists('selection_schedules');
    }
};
