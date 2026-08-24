<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_documents', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_vacancy_documents');
            $table->bigInteger('vacancy_id');
            $table->string('document_type', 64);
            $table->string('display_name', 255);
            $table->string('storage_reference', 512);
            $table->string('mime_type', 128)->nullable();
            $table->integer('size')->nullable();
            $table->bigInteger('uploaded_by');
            $table->timestampTz('archived_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('vacancy_id', 'fk_vacancy_documents_vacancy_id')
                ->references('id')->on('vacancies')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('uploaded_by', 'fk_vacancy_documents_uploaded_by')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE vacancy_documents RENAME CONSTRAINT vacancy_documents_pkey TO pk_vacancy_documents');
        DB::statement('CREATE INDEX idx_vacancy_documents_vacancy_id ON vacancy_documents (vacancy_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_documents');
    }
};
