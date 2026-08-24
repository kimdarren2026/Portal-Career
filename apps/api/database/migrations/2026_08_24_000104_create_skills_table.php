<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_skills');
            $table->string('name', 255);
            $table->string('normalized_name', 255);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique('normalized_name', 'uq_skills_normalized_name');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE skills RENAME CONSTRAINT skills_pkey TO pk_skills');
    }

    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
