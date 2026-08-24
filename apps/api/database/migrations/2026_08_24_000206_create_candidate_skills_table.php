<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_skills', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_candidate_skills');
            $table->bigInteger('candidate_profile_id');
            $table->bigInteger('skill_id');
            $table->string('proficiency_level', 64)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique(['candidate_profile_id', 'skill_id'], 'uq_candidate_skills_profile_skill');
            $table->foreign('candidate_profile_id', 'fk_candidate_skills_candidate_profile_id')
                ->references('id')->on('candidate_profiles')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('skill_id', 'fk_candidate_skills_skill_id')
                ->references('id')->on('skills')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE candidate_skills RENAME CONSTRAINT candidate_skills_pkey TO pk_candidate_skills');
        DB::statement('CREATE INDEX idx_candidate_skills_candidate_profile_id ON candidate_skills (candidate_profile_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_skills');
    }
};
