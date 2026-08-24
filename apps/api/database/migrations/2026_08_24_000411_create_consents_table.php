<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_consents');
            $table->bigInteger('user_id');
            $table->bigInteger('application_id')->nullable();
            $table->bigInteger('vacancy_id')->nullable();
            $table->bigInteger('receiving_company_id')->nullable();
            $table->bigInteger('receiving_organizational_unit_id')->nullable();
            $table->string('consent_type', 64);
            $table->string('consent_version', 64);
            $table->string('consent_text_hash_reference', 255);
            $table->text('purpose');
            $table->timestampTz('consented_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('user_id', 'fk_consents_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('application_id', 'fk_consents_application_id')
                ->references('id')->on('applications')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('vacancy_id', 'fk_consents_vacancy_id')
                ->references('id')->on('vacancies')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('receiving_company_id', 'fk_consents_receiving_company_id')
                ->references('id')->on('companies')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('receiving_organizational_unit_id', 'fk_consents_receiving_organizational_unit_id')
                ->references('id')->on('organizational_units')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE consents RENAME CONSTRAINT consents_pkey TO pk_consents');
        // The frozen physical rule is at-most-one; ownership matching is service-enforced.
        DB::statement('ALTER TABLE consents ADD CONSTRAINT chk_consents_receiver_xor CHECK (NOT (receiving_company_id IS NOT NULL AND receiving_organizational_unit_id IS NOT NULL))');
        DB::statement('CREATE INDEX idx_consents_application_id ON consents (application_id)');
        DB::statement('CREATE INDEX idx_consents_user_id ON consents (user_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
