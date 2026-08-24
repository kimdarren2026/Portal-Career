<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_educations', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_candidate_educations');
            $table->bigInteger('candidate_profile_id');
            $table->string('institution_name', 255);
            $table->bigInteger('study_program_id')->nullable();
            $table->string('study_program_name', 255)->nullable();
            $table->string('education_level', 64);
            $table->date('start_date')->nullable();
            $table->date('graduation_date')->nullable();
            $table->integer('graduation_year')->nullable();
            $table->string('score_summary', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('candidate_profile_id', 'fk_candidate_educations_candidate_profile_id')
                ->references('id')->on('candidate_profiles')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('study_program_id', 'fk_candidate_educations_study_program_id')
                ->references('id')->on('study_programs')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE candidate_educations RENAME CONSTRAINT candidate_educations_pkey TO pk_candidate_educations');
        DB::statement('CREATE INDEX idx_candidate_educations_candidate_profile_id ON candidate_educations (candidate_profile_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_educations');
    }
};
