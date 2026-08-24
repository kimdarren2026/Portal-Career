<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_vacancies');
            $table->string('vacancy_code', 64);
            $table->string('slug', 255);
            $table->string('vacancy_type', 32);
            $table->string('ownership_type', 16);
            $table->bigInteger('company_id')->nullable();
            $table->bigInteger('organizational_unit_id')->nullable();
            $table->bigInteger('created_by');
            $table->string('title', 255);
            $table->text('description');
            $table->text('responsibilities')->nullable();
            $table->string('employment_type', 64);
            $table->string('workplace_mode', 64)->nullable();
            $table->bigInteger('province_geographic_area_id')->nullable();
            $table->bigInteger('city_geographic_area_id')->nullable();
            $table->string('location', 255)->nullable();
            $table->integer('openings_count');
            $table->string('minimum_education', 255)->nullable();
            $table->string('experience_requirement', 255)->nullable();
            $table->decimal('salary_min', 14, 2)->nullable();
            $table->decimal('salary_max', 14, 2)->nullable();
            $table->string('salary_currency', 3)->nullable();
            $table->string('target_audience', 32);
            $table->string('application_method', 16);
            $table->string('external_ats_url', 2048)->nullable();
            $table->timestampTz('open_at')->nullable();
            $table->timestampTz('close_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->string('current_status', 32);
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique('vacancy_code', 'uq_vacancies_vacancy_code');
            $table->unique('slug', 'uq_vacancies_slug');
            $table->foreign('company_id', 'fk_vacancies_company_id')
                ->references('id')->on('companies')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('organizational_unit_id', 'fk_vacancies_organizational_unit_id')
                ->references('id')->on('organizational_units')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('created_by', 'fk_vacancies_created_by')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('province_geographic_area_id', 'fk_vacancies_province_geographic_area_id')
                ->references('id')->on('geographic_areas')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('city_geographic_area_id', 'fk_vacancies_city_geographic_area_id')
                ->references('id')->on('geographic_areas')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE vacancies RENAME CONSTRAINT vacancies_pkey TO pk_vacancies');
        DB::statement("ALTER TABLE vacancies ADD CONSTRAINT chk_vacancies_vacancy_type CHECK (vacancy_type IN ('CAMPUS_EMPLOYMENT', 'COMPANY_EMPLOYMENT', 'INTERNSHIP'))");
        DB::statement("ALTER TABLE vacancies ADD CONSTRAINT chk_vacancies_ownership_type CHECK (ownership_type IN ('COMPANY', 'CAMPUS'))");
        DB::statement("ALTER TABLE vacancies ADD CONSTRAINT chk_vacancies_target_audience CHECK (target_audience IN ('PUBLIC', 'ALUMNI_ONLY', 'FINAL_YEAR_AND_ALUMNI', 'INTERNAL'))");
        DB::statement("ALTER TABLE vacancies ADD CONSTRAINT chk_vacancies_application_method CHECK (application_method IN ('IN_PORTAL', 'EXTERNAL_ATS'))");
        DB::statement("ALTER TABLE vacancies ADD CONSTRAINT chk_vacancies_current_status CHECK (current_status IN ('DRAFT', 'PENDING_REVIEW', 'REVISION_REQUIRED', 'APPROVED', 'SCHEDULED', 'PUBLISHED', 'REJECTED', 'CLOSED', 'EXPIRED', 'SUSPENDED'))");
        // INV-018: the declared owner is the only populated owner reference.
        DB::statement("ALTER TABLE vacancies ADD CONSTRAINT chk_vacancies_ownership_xor CHECK ((ownership_type = 'COMPANY' AND company_id IS NOT NULL AND organizational_unit_id IS NULL) OR (ownership_type = 'CAMPUS' AND organizational_unit_id IS NOT NULL AND company_id IS NULL))");
        // INV-005 and the campus subset of INV-018.
        DB::statement("ALTER TABLE vacancies ADD CONSTRAINT chk_vacancies_campus_in_portal CHECK (ownership_type <> 'CAMPUS' OR application_method = 'IN_PORTAL')");
        DB::statement("ALTER TABLE vacancies ADD CONSTRAINT chk_vacancies_campus_status CHECK (ownership_type <> 'CAMPUS' OR current_status NOT IN ('PENDING_REVIEW', 'REVISION_REQUIRED', 'APPROVED', 'REJECTED'))");
        DB::statement("ALTER TABLE vacancies ADD CONSTRAINT chk_vacancies_external_url CHECK (application_method <> 'EXTERNAL_ATS' OR external_ats_url IS NOT NULL)");
        DB::statement('ALTER TABLE vacancies ADD CONSTRAINT chk_vacancies_close_after_open CHECK (open_at IS NULL OR close_at IS NULL OR close_at > open_at)');
        DB::statement('ALTER TABLE vacancies ADD CONSTRAINT chk_vacancies_salary_range CHECK (salary_min IS NULL OR salary_max IS NULL OR salary_max >= salary_min)');
        DB::statement('ALTER TABLE vacancies ADD CONSTRAINT chk_vacancies_openings_positive CHECK (openings_count >= 1)');

        DB::statement('CREATE INDEX idx_vacancies_company_id ON vacancies (company_id)');
        DB::statement('CREATE INDEX idx_vacancies_organizational_unit_id ON vacancies (organizational_unit_id)');
        DB::statement("CREATE INDEX idx_vacancies_public_listing ON vacancies (published_at DESC) WHERE current_status = 'PUBLISHED' AND target_audience <> 'INTERNAL'");
        DB::statement("CREATE INDEX idx_vacancies_public_filters ON vacancies (vacancy_type, employment_type, workplace_mode, city_geographic_area_id) WHERE current_status = 'PUBLISHED'");
        DB::statement("CREATE INDEX idx_vacancies_close_at ON vacancies (close_at) WHERE current_status = 'PUBLISHED'");
        DB::statement("CREATE INDEX idx_vacancies_open_at ON vacancies (open_at) WHERE current_status = 'SCHEDULED'");
        DB::statement("CREATE INDEX idx_vacancies_moderation_queue ON vacancies (current_status, created_at DESC) WHERE ownership_type = 'COMPANY'");
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancies');
    }
};
