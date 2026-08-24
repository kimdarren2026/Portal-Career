<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_links', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_candidate_links');
            $table->bigInteger('candidate_profile_id');
            $table->string('link_type', 32);
            $table->string('label', 255)->nullable();
            $table->string('url', 2048);
            $table->integer('sort_order');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique(['candidate_profile_id', 'url'], 'uq_candidate_links_profile_url');
            $table->foreign('candidate_profile_id', 'fk_candidate_links_candidate_profile_id')
                ->references('id')->on('candidate_profiles')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE candidate_links RENAME CONSTRAINT candidate_links_pkey TO pk_candidate_links');
        DB::statement("ALTER TABLE candidate_links ADD CONSTRAINT chk_candidate_links_link_type CHECK (link_type IN ('LINKEDIN', 'PORTFOLIO', 'PERSONAL_WEBSITE', 'PUBLICATION', 'OTHER'))");
        DB::statement('CREATE INDEX idx_candidate_links_candidate_profile_id ON candidate_links (candidate_profile_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_links');
    }
};
