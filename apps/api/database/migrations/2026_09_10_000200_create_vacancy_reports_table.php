<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PGC-V1 / PD-C — "Laporkan Lowongan" public vacancy reporting (anti-fraud).
 * A new entity with no prior representation. Reporter identity is optional
 * (anonymous reporters are permitted and never forced to register); an
 * authenticated reporter's `reporter_user_id` is attached server-side.
 * Lifecycle NEW -> UNDER_REVIEW -> ACTIONED | DISMISSED, Career Center owned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_reports', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_vacancy_reports');
            $table->bigInteger('vacancy_id');
            $table->bigInteger('reporter_user_id')->nullable();
            $table->string('reporter_name', 255)->nullable();
            $table->string('reporter_email', 255)->nullable();
            $table->string('reason', 64);
            $table->text('details')->nullable();
            $table->string('status', 32);
            $table->bigInteger('reviewed_by_user_id')->nullable();
            $table->timestampTz('review_started_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('vacancy_id', 'fk_vacancy_reports_vacancy_id')
                ->references('id')->on('vacancies')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('reporter_user_id', 'fk_vacancy_reports_reporter_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign('reviewed_by_user_id', 'fk_vacancy_reports_reviewed_by_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
        });

        DB::statement('ALTER TABLE vacancy_reports RENAME CONSTRAINT vacancy_reports_pkey TO pk_vacancy_reports');
        DB::statement("ALTER TABLE vacancy_reports ADD CONSTRAINT chk_vacancy_reports_reason CHECK (reason IN ('FRAUD_OR_SCAM', 'MISLEADING_INFORMATION', 'INAPPROPRIATE_CONTENT', 'INVALID_OR_EXPIRED_VACANCY', 'SUSPICIOUS_EXTERNAL_LINK', 'OTHER'))");
        DB::statement("ALTER TABLE vacancy_reports ADD CONSTRAINT chk_vacancy_reports_status CHECK (status IN ('NEW', 'UNDER_REVIEW', 'ACTIONED', 'DISMISSED'))");
        DB::statement('CREATE INDEX idx_vacancy_reports_vacancy_id ON vacancy_reports (vacancy_id)');
        DB::statement("CREATE INDEX idx_vacancy_reports_open ON vacancy_reports (created_at DESC) WHERE status IN ('NEW', 'UNDER_REVIEW')");
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_reports');
    }
};
