<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_application_documents');
            $table->bigInteger('application_id');
            $table->bigInteger('candidate_document_id');
            $table->timestampTz('shared_at');
            $table->string('snapshot_name', 255);
            $table->string('snapshot_storage_reference', 512);
            $table->string('snapshot_checksum', 255)->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreign('application_id', 'fk_application_documents_application_id')
                ->references('id')->on('applications')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('candidate_document_id', 'fk_application_documents_candidate_document_id')
                ->references('id')->on('candidate_documents')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE application_documents RENAME CONSTRAINT application_documents_pkey TO pk_application_documents');
        DB::statement('CREATE INDEX idx_application_documents_application_id ON application_documents (application_id)');
        DB::statement('CREATE INDEX idx_application_documents_candidate_document_id ON application_documents (candidate_document_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};
