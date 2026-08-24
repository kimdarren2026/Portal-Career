<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industries', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_industries');
            $table->string('code', 64);
            $table->string('name', 255);
            $table->boolean('active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique('code', 'uq_industries_code');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE industries RENAME CONSTRAINT industries_pkey TO pk_industries');
    }

    public function down(): void
    {
        Schema::dropIfExists('industries');
    }
};
