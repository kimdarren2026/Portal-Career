<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_apply_events', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_external_apply_events');
            $table->bigInteger('candidate_profile_id');
            $table->bigInteger('vacancy_id');
            $table->string('event_type', 32);
            $table->string('destination_url_reference', 2048);
            $table->timestampTz('started_at');
            $table->string('confirmation_status', 64);
            $table->string('confirmation_source', 64)->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->bigInteger('confirmed_by')->nullable();
            // The nullable column lands now; its FK is deliberately deferred to Phase 5.
            $table->bigInteger('consent_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('candidate_profile_id', 'fk_external_apply_events_candidate_profile_id')
                ->references('id')->on('candidate_profiles')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('vacancy_id', 'fk_external_apply_events_vacancy_id')
                ->references('id')->on('vacancies')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('confirmed_by', 'fk_external_apply_events_confirmed_by')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE external_apply_events RENAME CONSTRAINT external_apply_events_pkey TO pk_external_apply_events');
        DB::statement("ALTER TABLE external_apply_events ADD CONSTRAINT chk_external_apply_events_event_type CHECK (event_type IN ('EXTERNAL_APPLY_STARTED'))");
        DB::statement('CREATE INDEX idx_eae_candidate_profile_id ON external_apply_events (candidate_profile_id)');
        DB::statement('CREATE INDEX idx_eae_vacancy_id ON external_apply_events (vacancy_id)');
        DB::statement('CREATE INDEX idx_eae_vacancy_confirmation ON external_apply_events (vacancy_id, confirmation_status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('external_apply_events');
    }
};
