<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_profiles', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_candidate_profiles');
            $table->bigInteger('user_id');
            $table->string('headline', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->bigInteger('province_geographic_area_id')->nullable();
            $table->bigInteger('city_geographic_area_id')->nullable();
            $table->string('city', 255)->nullable();
            $table->string('province', 255)->nullable();
            $table->text('summary')->nullable();
            $table->string('current_candidate_type', 32);
            $table->string('preferred_employment_type', 64)->nullable();
            $table->string('preferred_workplace_mode', 64)->nullable();
            $table->string('preferred_location_note', 255)->nullable();
            $table->boolean('open_to_opportunities')->nullable();
            $table->timestampTz('profile_completed_at')->nullable();
            $table->timestampTz('anonymized_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique('user_id', 'uq_candidate_profiles_user');
            $table->foreign('user_id', 'fk_candidate_profiles_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('province_geographic_area_id', 'fk_candidate_profiles_province_geographic_area_id')
                ->references('id')->on('geographic_areas')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('city_geographic_area_id', 'fk_candidate_profiles_city_geographic_area_id')
                ->references('id')->on('geographic_areas')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE candidate_profiles RENAME CONSTRAINT candidate_profiles_pkey TO pk_candidate_profiles');
        // INV-028: a profile records only the frozen self-declared category set.
        DB::statement("ALTER TABLE candidate_profiles ADD CONSTRAINT chk_candidate_profiles_current_candidate_type CHECK (current_candidate_type IN ('EXTERNAL', 'FINAL_YEAR_STUDENT', 'ALUMNI'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_profiles');
    }
};
