<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_idempotency_keys');
            $table->string('idempotency_key', 255);
            $table->bigInteger('actor_user_id')->nullable();
            $table->string('actor_context', 64);
            $table->string('operation', 191);
            $table->string('request_fingerprint', 64);
            $table->string('state', 32);
            $table->smallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->string('response_reference', 512)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at')->default(DB::raw("(CURRENT_TIMESTAMP + INTERVAL '24 hours')"));
            $table->foreign('actor_user_id', 'fk_idempotency_keys_actor_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('cascade');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE idempotency_keys RENAME CONSTRAINT idempotency_keys_pkey TO pk_idempotency_keys');
        DB::statement("ALTER TABLE idempotency_keys ADD CONSTRAINT chk_idempotency_keys_state CHECK (state IN ('PROCESSING', 'COMPLETED', 'FAILED'))");
        DB::statement('CREATE UNIQUE INDEX uq_idempotency_keys_scope ON idempotency_keys (idempotency_key, operation, COALESCE(actor_user_id, 0))');
        DB::statement('CREATE INDEX idx_idempotency_keys_expires_at ON idempotency_keys (expires_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
