<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_applications');
            $table->string('application_code', 64);
            $table->bigInteger('candidate_profile_id');
            $table->bigInteger('vacancy_id');
            $table->string('current_status', 32);
            $table->bigInteger('current_stage_id')->nullable();
            $table->timestampTz('first_applied_at');
            $table->timestampTz('last_reopened_at')->nullable();
            $table->integer('reopen_count');
            $table->timestampTz('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->timestampTz('hired_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique('application_code', 'uq_applications_application_code');
            $table->unique(['candidate_profile_id', 'vacancy_id'], 'uq_applications_candidate_vacancy');
            $table->foreign('candidate_profile_id', 'fk_applications_candidate_profile_id')
                ->references('id')->on('candidate_profiles')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('vacancy_id', 'fk_applications_vacancy_id')
                ->references('id')->on('vacancies')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('current_stage_id', 'fk_applications_current_stage_id')
                ->references('id')->on('recruitment_stages')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE applications RENAME CONSTRAINT applications_pkey TO pk_applications');
        DB::statement("ALTER TABLE applications ADD CONSTRAINT chk_applications_current_status CHECK (current_status IN ('APPLIED', 'UNDER_REVIEW', 'SHORTLISTED', 'ASSESSMENT', 'INTERVIEW', 'OFFERED', 'HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'))");
        DB::statement('ALTER TABLE applications ADD CONSTRAINT chk_applications_reopen_count CHECK (reopen_count >= 0)');
        DB::statement("ALTER TABLE applications ADD CONSTRAINT chk_applications_withdrawn CHECK (current_status <> 'WITHDRAWN' OR withdrawn_at IS NOT NULL)");
        DB::statement('CREATE INDEX idx_applications_vacancy_id ON applications (vacancy_id)');
        DB::statement('CREATE INDEX idx_applications_candidate_profile_id ON applications (candidate_profile_id)');
        DB::statement('CREATE INDEX idx_applications_vacancy_status_applied ON applications (vacancy_id, current_status, first_applied_at DESC)');
        DB::statement('CREATE INDEX idx_applications_candidate_applied ON applications (candidate_profile_id, first_applied_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
