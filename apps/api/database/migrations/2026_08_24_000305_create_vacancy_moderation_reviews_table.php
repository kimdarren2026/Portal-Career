<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_moderation_reviews', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_vacancy_moderation_reviews');
            $table->bigInteger('vacancy_id');
            $table->bigInteger('reviewer_user_id');
            $table->string('action', 32);
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('reason_category', 255)->nullable();
            $table->text('recruiter_visible_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestampTz('reviewed_at');
            $table->foreign('vacancy_id', 'fk_vacancy_moderation_reviews_vacancy_id')
                ->references('id')->on('vacancies')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('reviewer_user_id', 'fk_vacancy_moderation_reviews_reviewer_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE vacancy_moderation_reviews RENAME CONSTRAINT vacancy_moderation_reviews_pkey TO pk_vacancy_moderation_reviews');
        DB::statement("ALTER TABLE vacancy_moderation_reviews ADD CONSTRAINT chk_vacancy_moderation_reviews_action CHECK (action IN ('SUBMIT', 'REQUEST_REVISION', 'APPROVE', 'REJECT', 'SUSPEND', 'RESTORE', 'CLOSE'))");
        // INV-029: adverse moderation actions need a category and recruiter-visible note.
        DB::statement("ALTER TABLE vacancy_moderation_reviews ADD CONSTRAINT chk_vacancy_moderation_reviews_reason_required CHECK (action NOT IN ('REQUEST_REVISION', 'REJECT', 'SUSPEND') OR (reason_category IS NOT NULL AND recruiter_visible_note IS NOT NULL))");
        DB::statement('CREATE INDEX idx_vmr_vacancy_reviewed ON vacancy_moderation_reviews (vacancy_id, reviewed_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_moderation_reviews');
    }
};
