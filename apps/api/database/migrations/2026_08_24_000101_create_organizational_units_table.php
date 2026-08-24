<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizational_units', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_organizational_units');
            $table->bigInteger('parent_unit_id')->nullable();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->boolean('active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique('code', 'uq_organizational_units_code');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE organizational_units RENAME CONSTRAINT organizational_units_pkey TO pk_organizational_units');

        Schema::table('organizational_units', function (Blueprint $table) {
            $table->foreign('parent_unit_id', 'fk_organizational_units_parent_unit_id')
                ->references('id')
                ->on('organizational_units')
                ->onUpdate('no action')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_units');
    }
};
