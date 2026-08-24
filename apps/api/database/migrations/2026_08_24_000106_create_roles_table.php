<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_roles');
            $table->string('code', 64);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique('code', 'uq_roles_code');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE roles RENAME CONSTRAINT roles_pkey TO pk_roles');
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
