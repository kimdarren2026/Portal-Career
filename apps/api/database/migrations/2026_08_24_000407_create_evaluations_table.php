<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_evaluations');
            $table->bigInteger('application_id');
            $table->bigInteger('recruitment_stage_id');
            $table->bigInteger('evaluator_user_id');
            $table->string('recommendation', 64)->nullable();
            $table->text('comments')->nullable();
            $table->decimal('total_score', 6, 2)->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('application_id', 'fk_evaluations_application_id')
                ->references('id')->on('applications')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('recruitment_stage_id', 'fk_evaluations_recruitment_stage_id')
                ->references('id')->on('recruitment_stages')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('evaluator_user_id', 'fk_evaluations_evaluator_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE evaluations RENAME CONSTRAINT evaluations_pkey TO pk_evaluations');
        DB::statement('CREATE INDEX idx_evaluations_application_id ON evaluations (application_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
