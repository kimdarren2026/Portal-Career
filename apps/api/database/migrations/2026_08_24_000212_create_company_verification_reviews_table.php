<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_verification_reviews', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_company_verification_reviews');
            $table->bigInteger('company_id');
            $table->bigInteger('reviewer_user_id');
            $table->string('action', 32);
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('reason_category', 255)->nullable();
            $table->text('recruiter_visible_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestampTz('reviewed_at');
            $table->foreign('company_id', 'fk_company_verification_reviews_company_id')
                ->references('id')->on('companies')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('reviewer_user_id', 'fk_company_verification_reviews_reviewer_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE company_verification_reviews RENAME CONSTRAINT company_verification_reviews_pkey TO pk_company_verification_reviews');
        DB::statement("ALTER TABLE company_verification_reviews ADD CONSTRAINT chk_company_verification_reviews_action CHECK (action IN ('SUBMIT', 'REQUEST_REVISION', 'VERIFY', 'REJECT', 'SUSPEND', 'RESTORE'))");
        // INV-029: only adverse review actions require recruiter-visible reasons.
        DB::statement("ALTER TABLE company_verification_reviews ADD CONSTRAINT chk_company_verification_reviews_reason_required CHECK (action NOT IN ('REQUEST_REVISION', 'REJECT', 'SUSPEND') OR (reason_category IS NOT NULL AND recruiter_visible_note IS NOT NULL))");
        DB::statement('CREATE INDEX idx_cvr_company_reviewed ON company_verification_reviews (company_id, reviewed_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_verification_reviews');
    }
};
