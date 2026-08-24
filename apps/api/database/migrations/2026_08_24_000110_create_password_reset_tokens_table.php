<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_password_reset_tokens');
            $table->bigInteger('user_id');
            $table->string('token_hash', 255);
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique('token_hash', 'uq_password_reset_tokens_hash');
            $table->foreign('user_id', 'fk_password_reset_tokens_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('no action')
                ->onDelete('cascade');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE password_reset_tokens RENAME CONSTRAINT password_reset_tokens_pkey TO pk_password_reset_tokens');
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
