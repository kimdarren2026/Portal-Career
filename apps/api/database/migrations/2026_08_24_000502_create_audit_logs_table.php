<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_audit_logs');
            $table->bigInteger('actor_user_id')->nullable();
            $table->string('action', 128);
            $table->string('object_type', 64);
            $table->bigInteger('object_id')->nullable();
            $table->jsonb('change_summary')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->jsonb('user_agent_device_metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('actor_user_id', 'fk_audit_logs_actor_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE audit_logs RENAME CONSTRAINT audit_logs_pkey TO pk_audit_logs');
        DB::statement('CREATE INDEX idx_audit_logs_created_at ON audit_logs (created_at DESC)');
        DB::statement('CREATE INDEX idx_audit_logs_actor ON audit_logs (actor_user_id, created_at DESC)');
        DB::statement('CREATE INDEX idx_audit_logs_object ON audit_logs (object_type, object_id, created_at DESC)');
        DB::statement('CREATE INDEX idx_audit_logs_action ON audit_logs (action, created_at DESC)');
        DB::statement('CREATE INDEX idx_audit_logs_correlation ON audit_logs (correlation_id) WHERE correlation_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
