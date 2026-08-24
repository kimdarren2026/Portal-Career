<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_programs', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_study_programs');
            $table->string('code', 64);
            $table->string('name', 255);
            $table->bigInteger('organizational_unit_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique('code', 'uq_study_programs_code');
            $table->foreign('organizational_unit_id', 'fk_study_programs_organizational_unit_id')
                ->references('id')
                ->on('organizational_units')
                ->onUpdate('no action')
                ->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE study_programs RENAME CONSTRAINT study_programs_pkey TO pk_study_programs');
    }

    public function down(): void
    {
        Schema::dropIfExists('study_programs');
    }
};
