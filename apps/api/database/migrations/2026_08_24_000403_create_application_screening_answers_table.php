<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_screening_answers', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_application_screening_answers');
            $table->bigInteger('application_id');
            $table->bigInteger('screening_question_id');
            $table->text('answer_text')->nullable();
            $table->boolean('answer_boolean')->nullable();
            $table->decimal('answer_number', 14, 2)->nullable();
            $table->string('answer_option', 255)->nullable();
            $table->timestampTz('answered_at');
            $table->unique(['application_id', 'screening_question_id'], 'uq_application_screening_answers_app_question');
            $table->foreign('application_id', 'fk_application_screening_answers_application_id')
                ->references('id')->on('applications')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('screening_question_id', 'fk_application_screening_answers_screening_question_id')
                ->references('id')->on('vacancy_screening_questions')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE application_screening_answers RENAME CONSTRAINT application_screening_answers_pkey TO pk_application_screening_answers');
        DB::statement('ALTER TABLE application_screening_answers ADD CONSTRAINT chk_application_screening_answers_one_value CHECK ((answer_text IS NOT NULL)::int + (answer_boolean IS NOT NULL)::int + (answer_number IS NOT NULL)::int + (answer_option IS NOT NULL)::int = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('application_screening_answers');
    }
};
