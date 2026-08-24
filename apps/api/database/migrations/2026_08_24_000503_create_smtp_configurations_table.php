<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_configurations', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_smtp_configurations');
            $table->string('host', 255);
            $table->integer('port');
            $table->string('encryption_mode', 16);
            $table->string('username', 255)->nullable();
            $table->text('encrypted_password')->nullable();
            $table->string('from_address', 255);
            $table->string('from_name', 255)->nullable();
            $table->string('reply_to_address', 255)->nullable();
            $table->integer('timeout_seconds')->nullable();
            $table->integer('max_attempts');
            $table->integer('retry_backoff_seconds');
            $table->boolean('is_active')->default(false);
            $table->timestampTz('last_tested_at')->nullable();
            $table->string('last_test_result', 16)->nullable();
            $table->bigInteger('updated_by_user_id');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('updated_by_user_id', 'fk_smtp_configurations_updated_by_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE smtp_configurations RENAME CONSTRAINT smtp_configurations_pkey TO pk_smtp_configurations');
        DB::statement("ALTER TABLE smtp_configurations ADD CONSTRAINT chk_smtp_configurations_encryption_mode CHECK (encryption_mode IN ('NONE', 'STARTTLS', 'TLS'))");
        DB::statement("ALTER TABLE smtp_configurations ADD CONSTRAINT chk_smtp_configurations_last_test_result CHECK (last_test_result IS NULL OR last_test_result IN ('NOT_TESTED', 'SUCCESS', 'FAILURE'))");
        DB::statement('ALTER TABLE smtp_configurations ADD CONSTRAINT chk_smtp_configurations_port CHECK (port BETWEEN 1 AND 65535)');
        DB::statement('ALTER TABLE smtp_configurations ADD CONSTRAINT chk_smtp_configurations_attempts CHECK (max_attempts BETWEEN 1 AND 20)');
        DB::statement('ALTER TABLE smtp_configurations ADD CONSTRAINT chk_smtp_configurations_backoff CHECK (retry_backoff_seconds BETWEEN 1 AND 86400)');
        DB::statement('ALTER TABLE smtp_configurations ADD CONSTRAINT chk_smtp_configurations_timeout CHECK (timeout_seconds IS NULL OR timeout_seconds BETWEEN 1 AND 600)');
        DB::statement('CREATE UNIQUE INDEX uq_smtp_configurations_active ON smtp_configurations ((true)) WHERE is_active');
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_configurations');
    }
};
