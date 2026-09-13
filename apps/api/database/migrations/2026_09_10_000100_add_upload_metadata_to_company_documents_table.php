<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PGC-V1 / PD-D — the frozen `POST /companies/{company}/documents` upload
 * runtime is activated. The base `company_documents` table records the legal
 * fields (`document_type`, `document_number`, `issued_at`, `expires_at`) but
 * not what was actually uploaded, which the file policy and audit attribution
 * need. Purely additive nullable columns — no existing invariant (INV-030,
 * INV-038) or constraint is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_documents', function (Blueprint $table) {
            $table->string('original_filename', 255)->nullable()->after('storage_reference');
            $table->string('mime_type', 128)->nullable()->after('original_filename');
            $table->integer('file_size_bytes')->nullable()->after('mime_type');
            $table->bigInteger('uploaded_by_user_id')->nullable()->after('file_size_bytes');
            $table->foreign('uploaded_by_user_id', 'fk_company_documents_uploaded_by_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('company_documents', function (Blueprint $table) {
            $table->dropForeign('fk_company_documents_uploaded_by_user_id');
            $table->dropColumn(['original_filename', 'mime_type', 'file_size_bytes', 'uploaded_by_user_id']);
        });
    }
};
