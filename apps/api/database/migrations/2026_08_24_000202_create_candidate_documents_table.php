<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_candidate_documents');
            $table->bigInteger('candidate_profile_id');
            $table->string('document_type', 64);
            $table->string('display_name', 255);
            $table->string('storage_reference', 512);
            $table->string('mime_type', 128);
            $table->integer('size');
            $table->string('checksum', 255)->nullable();
            $table->timestampTz('uploaded_at');
            $table->timestampTz('archived_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('candidate_profile_id', 'fk_candidate_documents_candidate_profile_id')
                ->references('id')->on('candidate_profiles')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE candidate_documents RENAME CONSTRAINT candidate_documents_pkey TO pk_candidate_documents');
        DB::statement('CREATE INDEX idx_candidate_documents_profile_type ON candidate_documents (candidate_profile_id, document_type)');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_documents');
    }
};
