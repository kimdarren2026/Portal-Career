<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_verifications', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_candidate_verifications');
            $table->bigInteger('candidate_profile_id');
            $table->string('verification_type', 32);
            $table->string('status', 32);
            $table->string('student_number', 64)->nullable();
            $table->bigInteger('program_study_id')->nullable();
            $table->integer('graduation_year')->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->bigInteger('verified_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('candidate_profile_id', 'fk_candidate_verifications_candidate_profile_id')
                ->references('id')->on('candidate_profiles')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('program_study_id', 'fk_candidate_verifications_program_study_id')
                ->references('id')->on('study_programs')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('verified_by', 'fk_candidate_verifications_verified_by')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE candidate_verifications RENAME CONSTRAINT candidate_verifications_pkey TO pk_candidate_verifications');
        // INV-028: verification type and result remain independent, frozen values.
        DB::statement("ALTER TABLE candidate_verifications ADD CONSTRAINT chk_candidate_verifications_verification_type CHECK (verification_type IN ('ALUMNI', 'FINAL_YEAR_STUDENT'))");
        DB::statement("ALTER TABLE candidate_verifications ADD CONSTRAINT chk_candidate_verifications_status CHECK (status IN ('NOT_VERIFIED', 'PENDING', 'VERIFIED', 'MISMATCH_MANUAL_REVIEW'))");
        // FR-CAN-004: a manual mismatch must retain its candidate-visible reason.
        DB::statement("ALTER TABLE candidate_verifications ADD CONSTRAINT chk_candidate_verifications_rejection_reason CHECK (status <> 'MISMATCH_MANUAL_REVIEW' OR rejection_reason IS NOT NULL)");
        DB::statement('CREATE INDEX idx_candidate_verifications_profile_type_status ON candidate_verifications (candidate_profile_id, verification_type, status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_verifications');
    }
};
