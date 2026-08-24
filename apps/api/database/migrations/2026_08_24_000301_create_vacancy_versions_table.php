<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_versions', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_vacancy_versions');
            $table->bigInteger('vacancy_id');
            $table->integer('version_number');
            $table->jsonb('snapshot');
            $table->bigInteger('created_by');
            $table->timestampTz('created_at')->useCurrent();
            $table->text('change_reason')->nullable();
            $table->unique(['vacancy_id', 'version_number'], 'uq_vacancy_versions_vacancy_version');
            $table->foreign('vacancy_id', 'fk_vacancy_versions_vacancy_id')
                ->references('id')->on('vacancies')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('created_by', 'fk_vacancy_versions_created_by')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE vacancy_versions RENAME CONSTRAINT vacancy_versions_pkey TO pk_vacancy_versions');
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_versions');
    }
};
