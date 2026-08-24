<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_documents', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_company_documents');
            $table->bigInteger('company_id');
            $table->string('document_type', 64);
            $table->string('document_number', 255)->nullable();
            $table->string('storage_reference', 512);
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 64);
            $table->timestampTz('first_submitted_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->bigInteger('superseded_by_document_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('company_id', 'fk_company_documents_company_id')
                ->references('id')->on('companies')->onUpdate('no action')->onDelete('restrict');
            $table->unique('superseded_by_document_id', 'uq_company_documents_superseded_by_document');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE company_documents RENAME CONSTRAINT company_documents_pkey TO pk_company_documents');
        Schema::table('company_documents', function (Blueprint $table) {
            $table->foreign('superseded_by_document_id', 'fk_company_documents_superseded_by_document_id')
                ->references('id')->on('company_documents')->onUpdate('no action')->onDelete('restrict');
        });

        // INV-038: dates and successor references preserve an auditable chain.
        DB::statement('ALTER TABLE company_documents ADD CONSTRAINT chk_company_documents_expiry CHECK (issued_at IS NULL OR expires_at IS NULL OR expires_at >= issued_at)');
        DB::statement('ALTER TABLE company_documents ADD CONSTRAINT chk_company_documents_supersede CHECK ((superseded_at IS NULL AND superseded_by_document_id IS NULL) OR (superseded_at IS NOT NULL AND superseded_by_document_id IS NOT NULL))');
        DB::statement('ALTER TABLE company_documents ADD CONSTRAINT chk_company_documents_no_self_supersede CHECK (superseded_by_document_id IS NULL OR superseded_by_document_id <> id)');
        DB::statement('CREATE INDEX idx_company_documents_company_id ON company_documents (company_id)');
        DB::statement('CREATE INDEX idx_company_documents_superseded_by ON company_documents (superseded_by_document_id) WHERE superseded_by_document_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_company_documents_company_current ON company_documents (company_id) WHERE superseded_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_documents');
    }
};
