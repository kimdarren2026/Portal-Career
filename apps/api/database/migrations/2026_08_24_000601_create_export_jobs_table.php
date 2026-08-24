<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_export_jobs');
            $table->bigInteger('requested_by_user_id');
            $table->string('export_type', 64);
            $table->jsonb('parameters');
            $table->jsonb('authorized_scope');
            $table->string('status', 32);
            $table->string('storage_reference', 512)->nullable();
            $table->integer('row_count')->nullable();
            $table->text('failure_summary')->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('downloaded_at')->nullable();
            $table->integer('download_count')->default(0);
            $table->foreign('requested_by_user_id', 'fk_export_jobs_requested_by_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE export_jobs RENAME CONSTRAINT export_jobs_pkey TO pk_export_jobs');
        DB::statement("ALTER TABLE export_jobs ADD CONSTRAINT chk_export_jobs_status CHECK (status IN ('PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'EXPIRED'))");
        DB::statement('CREATE INDEX idx_export_jobs_status_requested ON export_jobs (status, requested_at)');
        DB::statement('CREATE INDEX idx_export_jobs_requested_by ON export_jobs (requested_by_user_id, requested_at DESC)');
        DB::statement("CREATE INDEX idx_export_jobs_expires_at ON export_jobs (expires_at) WHERE status = 'COMPLETED'");
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
