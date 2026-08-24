<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_companies');
            $table->string('name', 200);
            $table->string('normalized_name', 255);
            $table->bigInteger('organization_type_id')->nullable();
            $table->bigInteger('industry_id')->nullable();
            $table->string('website', 2048)->nullable();
            $table->string('official_email', 255)->nullable();
            $table->string('official_phone', 32)->nullable();
            $table->text('address')->nullable();
            $table->bigInteger('province_geographic_area_id')->nullable();
            $table->bigInteger('city_geographic_area_id')->nullable();
            $table->string('legal_identifier', 255)->nullable();
            $table->string('logo_storage_reference', 512)->nullable();
            $table->string('verification_status', 32);
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->bigInteger('created_by');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('organization_type_id', 'fk_companies_organization_type_id')
                ->references('id')->on('organization_types')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('industry_id', 'fk_companies_industry_id')
                ->references('id')->on('industries')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('province_geographic_area_id', 'fk_companies_province_geographic_area_id')
                ->references('id')->on('geographic_areas')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('city_geographic_area_id', 'fk_companies_city_geographic_area_id')
                ->references('id')->on('geographic_areas')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('created_by', 'fk_companies_created_by')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE companies RENAME CONSTRAINT companies_pkey TO pk_companies');
        // INV-034 deliberately leaves normalized_name non-unique.
        DB::statement("ALTER TABLE companies ADD CONSTRAINT chk_companies_verification_status CHECK (verification_status IN ('DRAFT', 'PENDING_VERIFICATION', 'REVISION_REQUIRED', 'VERIFIED', 'REJECTED', 'SUSPENDED'))");
        DB::statement('CREATE INDEX idx_companies_verification_queue ON companies (verification_status, created_at DESC)');
        DB::statement('CREATE INDEX idx_companies_normalized_name ON companies (normalized_name)');
        DB::statement('CREATE INDEX idx_companies_legal_identifier ON companies (legal_identifier) WHERE legal_identifier IS NOT NULL');
        DB::statement('CREATE INDEX idx_companies_official_email ON companies (official_email) WHERE official_email IS NOT NULL');
        DB::statement('CREATE INDEX idx_companies_official_phone ON companies (official_phone) WHERE official_phone IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
