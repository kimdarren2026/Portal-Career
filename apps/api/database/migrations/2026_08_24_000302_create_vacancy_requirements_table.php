<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_requirements', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_vacancy_requirements');
            $table->bigInteger('vacancy_id');
            $table->string('requirement_type', 32);
            $table->string('education_level', 64)->nullable();
            $table->bigInteger('study_program_id')->nullable();
            $table->bigInteger('skill_id')->nullable();
            $table->integer('minimum_years_experience')->nullable();
            $table->string('value_text', 255)->nullable();
            $table->text('note')->nullable();
            $table->boolean('required');
            $table->integer('sort_order');
            $table->foreign('vacancy_id', 'fk_vacancy_requirements_vacancy_id')
                ->references('id')->on('vacancies')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('study_program_id', 'fk_vacancy_requirements_study_program_id')
                ->references('id')->on('study_programs')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('skill_id', 'fk_vacancy_requirements_skill_id')
                ->references('id')->on('skills')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE vacancy_requirements RENAME CONSTRAINT vacancy_requirements_pkey TO pk_vacancy_requirements');
        DB::statement("ALTER TABLE vacancy_requirements ADD CONSTRAINT chk_vacancy_requirements_requirement_type CHECK (requirement_type IN ('EDUCATION', 'STUDY_PROGRAM', 'EXPERIENCE', 'SKILL', 'CERTIFICATION', 'OTHER_QUALIFICATION'))");
        DB::statement("ALTER TABLE vacancy_requirements ADD CONSTRAINT chk_vacancy_requirements_typed_value CHECK ((requirement_type = 'EDUCATION' AND education_level IS NOT NULL) OR (requirement_type = 'STUDY_PROGRAM' AND study_program_id IS NOT NULL) OR (requirement_type = 'SKILL' AND skill_id IS NOT NULL) OR (requirement_type = 'EXPERIENCE' AND minimum_years_experience IS NOT NULL) OR (requirement_type IN ('CERTIFICATION', 'OTHER_QUALIFICATION') AND value_text IS NOT NULL))");
        DB::statement('CREATE INDEX idx_vacancy_requirements_vacancy_sort ON vacancy_requirements (vacancy_id, sort_order)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_requirements');
    }
};
