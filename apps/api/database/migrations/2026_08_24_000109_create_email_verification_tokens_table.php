<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_email_verification_tokens');
            $table->bigInteger('user_id');
            $table->string('token_hash', 255);
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique('token_hash', 'uq_email_verification_tokens_hash');
            $table->foreign('user_id', 'fk_email_verification_tokens_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('no action')
                ->onDelete('cascade');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE email_verification_tokens RENAME CONSTRAINT email_verification_tokens_pkey TO pk_email_verification_tokens');
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
    }
};
