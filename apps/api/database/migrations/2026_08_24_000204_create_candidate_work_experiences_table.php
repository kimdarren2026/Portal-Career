<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_work_experiences', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_candidate_work_experiences');
            $table->bigInteger('candidate_profile_id');
            $table->string('employer_name', 255);
            $table->string('position_title', 255);
            $table->string('employment_type', 64)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current');
            $table->text('description')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('candidate_profile_id', 'fk_candidate_work_experiences_candidate_profile_id')
                ->references('id')->on('candidate_profiles')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE candidate_work_experiences RENAME CONSTRAINT candidate_work_experiences_pkey TO pk_candidate_work_experiences');
        // An ongoing experience cannot also have an end date.
        DB::statement('ALTER TABLE candidate_work_experiences ADD CONSTRAINT chk_candidate_work_experiences_current CHECK (NOT is_current OR end_date IS NULL)');
        DB::statement('CREATE INDEX idx_candidate_work_experiences_candidate_profile_id ON candidate_work_experiences (candidate_profile_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_work_experiences');
    }
};
