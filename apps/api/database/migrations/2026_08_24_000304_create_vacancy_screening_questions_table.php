<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_screening_questions', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_vacancy_screening_questions');
            $table->bigInteger('vacancy_id');
            $table->text('question_text');
            $table->string('question_type', 32);
            $table->boolean('required');
            $table->jsonb('options_definition')->nullable();
            $table->integer('sort_order');
            $table->boolean('active');
            $table->foreign('vacancy_id', 'fk_vacancy_screening_questions_vacancy_id')
                ->references('id')->on('vacancies')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE vacancy_screening_questions RENAME CONSTRAINT vacancy_screening_questions_pkey TO pk_vacancy_screening_questions');
        DB::statement("ALTER TABLE vacancy_screening_questions ADD CONSTRAINT chk_vacancy_screening_questions_question_type CHECK (question_type IN ('SHORT_TEXT', 'LONG_TEXT', 'YES_NO', 'SINGLE_CHOICE', 'NUMBER'))");
        DB::statement('CREATE INDEX idx_vsq_vacancy_sort_active ON vacancy_screening_questions (vacancy_id, sort_order) WHERE active');
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_screening_questions');
    }
};
