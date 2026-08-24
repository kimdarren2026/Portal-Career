<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_outbox', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_email_outbox');
            $table->string('recipient', 255);
            $table->string('template_reference', 255);
            $table->jsonb('payload_reference')->nullable();
            $table->string('related_object_type', 64)->nullable();
            $table->bigInteger('related_object_id')->nullable();
            $table->string('status', 32);
            $table->integer('attempt_count')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->text('last_error_summary')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE email_outbox RENAME CONSTRAINT email_outbox_pkey TO pk_email_outbox');
        DB::statement("ALTER TABLE email_outbox ADD CONSTRAINT chk_email_outbox_status CHECK (status IN ('PENDING', 'PROCESSING', 'SENT', 'FAILED_RETRYABLE', 'DEAD_LETTER'))");
        DB::statement('ALTER TABLE email_outbox ADD CONSTRAINT chk_email_outbox_attempts CHECK (attempt_count >= 0)');
        DB::statement("CREATE INDEX idx_email_outbox_due ON email_outbox (next_attempt_at) WHERE status IN ('PENDING', 'FAILED_RETRYABLE')");
        DB::statement("CREATE INDEX idx_email_outbox_dead_letter ON email_outbox (created_at DESC) WHERE status = 'DEAD_LETTER'");
    }

    public function down(): void
    {
        Schema::dropIfExists('email_outbox');
    }
};
