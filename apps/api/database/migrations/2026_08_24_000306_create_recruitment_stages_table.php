<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_stages', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_recruitment_stages');
            $table->bigInteger('vacancy_id');
            $table->string('name', 255);
            $table->string('stage_type', 64);
            $table->integer('sort_order');
            $table->boolean('active');
            $table->string('candidate_visible_label', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('vacancy_id', 'fk_recruitment_stages_vacancy_id')
                ->references('id')->on('vacancies')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE recruitment_stages RENAME CONSTRAINT recruitment_stages_pkey TO pk_recruitment_stages');
        DB::statement('CREATE INDEX idx_recruitment_stages_vacancy_sort ON recruitment_stages (vacancy_id, sort_order)');
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_stages');
    }
};
