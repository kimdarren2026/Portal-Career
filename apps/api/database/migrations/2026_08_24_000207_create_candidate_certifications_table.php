<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_certifications', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_candidate_certifications');
            $table->bigInteger('candidate_profile_id');
            $table->string('certification_name', 255);
            $table->string('issuer_name', 255);
            $table->string('credential_identifier', 255)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('credential_url', 2048)->nullable();
            $table->bigInteger('document_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('candidate_profile_id', 'fk_candidate_certifications_candidate_profile_id')
                ->references('id')->on('candidate_profiles')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('document_id', 'fk_candidate_certifications_document_id')
                ->references('id')->on('candidate_documents')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE candidate_certifications RENAME CONSTRAINT candidate_certifications_pkey TO pk_candidate_certifications');
        DB::statement('ALTER TABLE candidate_certifications ADD CONSTRAINT chk_candidate_certifications_expiry CHECK (issued_at IS NULL OR expires_at IS NULL OR expires_at >= issued_at)');
        DB::statement('CREATE INDEX idx_candidate_certifications_candidate_profile_id ON candidate_certifications (candidate_profile_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_certifications');
    }
};
