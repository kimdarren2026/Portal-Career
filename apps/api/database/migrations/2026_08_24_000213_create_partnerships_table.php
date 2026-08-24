<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnerships', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_partnerships');
            $table->bigInteger('company_id');
            $table->string('partnership_type', 64);
            $table->string('agreement_number', 255)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 64);
            $table->string('campus_pic', 255)->nullable();
            $table->string('company_pic', 255)->nullable();
            $table->string('document_reference', 512)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('company_id', 'fk_partnerships_company_id')
                ->references('id')->on('companies')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE partnerships RENAME CONSTRAINT partnerships_pkey TO pk_partnerships');
        DB::statement('CREATE INDEX idx_partnerships_company_status ON partnerships (company_id, status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('partnerships');
    }
};
