<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_offers');
            $table->bigInteger('application_id');
            $table->bigInteger('offered_by_user_id');
            $table->timestampTz('offered_at');
            $table->timestampTz('response_deadline')->nullable();
            $table->text('note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('document_reference', 512)->nullable();
            $table->string('status', 32);
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampTz('offer_accepted_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('application_id', 'fk_offers_application_id')
                ->references('id')->on('applications')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('offered_by_user_id', 'fk_offers_offered_by_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE offers RENAME CONSTRAINT offers_pkey TO pk_offers');
        DB::statement("ALTER TABLE offers ADD CONSTRAINT chk_offers_status CHECK (status IN ('DRAFT', 'SENT', 'PENDING_RESPONSE', 'ACCEPTED', 'REJECTED', 'EXPIRED'))");
        DB::statement("ALTER TABLE offers ADD CONSTRAINT chk_offers_accepted_at CHECK (status <> 'ACCEPTED' OR offer_accepted_at IS NOT NULL)");
        DB::statement("CREATE UNIQUE INDEX uq_offers_application_accepted ON offers (application_id) WHERE status = 'ACCEPTED'");
        DB::statement('CREATE INDEX idx_offers_application_id ON offers (application_id)');
        DB::statement("CREATE INDEX idx_offers_accepted ON offers (offer_accepted_at) WHERE status = 'ACCEPTED'");
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
