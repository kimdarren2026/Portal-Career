<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_saved_vacancies', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_candidate_saved_vacancies');
            $table->bigInteger('candidate_profile_id');
            $table->bigInteger('vacancy_id');
            $table->timestampTz('saved_at');
            $table->unique(['candidate_profile_id', 'vacancy_id'], 'uq_candidate_saved_vacancies_profile_vacancy');
            $table->foreign('candidate_profile_id', 'fk_candidate_saved_vacancies_candidate_profile_id')
                ->references('id')->on('candidate_profiles')->onUpdate('no action')->onDelete('cascade');
            $table->foreign('vacancy_id', 'fk_candidate_saved_vacancies_vacancy_id')
                ->references('id')->on('vacancies')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE candidate_saved_vacancies RENAME CONSTRAINT candidate_saved_vacancies_pkey TO pk_candidate_saved_vacancies');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_saved_vacancies');
    }
};
