<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geographic_areas', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_geographic_areas');
            $table->bigInteger('parent_geographic_area_id')->nullable();
            $table->string('code', 64)->nullable();
            $table->string('name', 255);
            $table->string('area_type', 16);
            $table->boolean('active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name; retain the
        // frozen pk_<table> convention for deterministic migrations.
        DB::statement('ALTER TABLE geographic_areas RENAME CONSTRAINT geographic_areas_pkey TO pk_geographic_areas');

        Schema::table('geographic_areas', function (Blueprint $table) {
            $table->foreign('parent_geographic_area_id', 'fk_geographic_areas_parent_geographic_area_id')
                ->references('id')
                ->on('geographic_areas')
                ->onUpdate('no action')
                ->onDelete('restrict');
        });

        // Frozen value set from DATABASE_SCHEMA.md §5.
        DB::statement("ALTER TABLE geographic_areas ADD CONSTRAINT chk_geographic_areas_area_type CHECK (area_type IN ('PROVINCE', 'CITY'))");
        DB::statement('CREATE UNIQUE INDEX uq_geographic_areas_code ON geographic_areas (code) WHERE code IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('geographic_areas');
    }
};
