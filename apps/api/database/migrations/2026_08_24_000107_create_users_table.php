<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_users');
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('email_normalized', 255);
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('status', 32);
            $table->string('phone', 32)->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampTz('anonymized_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique('email_normalized', 'uq_users_email_normalized');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE users RENAME CONSTRAINT users_pkey TO pk_users');

        // INV-001: email_normalized is the sole authoritative identity key.
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_status CHECK (status IN ('PENDING_EMAIL_VERIFICATION', 'ACTIVE', 'SUSPENDED', 'DISABLED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
