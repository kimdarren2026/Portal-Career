<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_organizations', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_candidate_organizations');
            $table->bigInteger('candidate_profile_id');
            $table->string('organization_name', 255);
            $table->string('role_title', 255);
            $table->string('organization_type', 64)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current');
            $table->text('description')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('candidate_profile_id', 'fk_candidate_organizations_candidate_profile_id')
                ->references('id')->on('candidate_profiles')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE candidate_organizations RENAME CONSTRAINT candidate_organizations_pkey TO pk_candidate_organizations');
        // An ongoing organization activity cannot also have an end date.
        DB::statement('ALTER TABLE candidate_organizations ADD CONSTRAINT chk_candidate_organizations_current CHECK (NOT is_current OR end_date IS NULL)');
        DB::statement('CREATE INDEX idx_candidate_organizations_candidate_profile_id ON candidate_organizations (candidate_profile_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_organizations');
    }
};
