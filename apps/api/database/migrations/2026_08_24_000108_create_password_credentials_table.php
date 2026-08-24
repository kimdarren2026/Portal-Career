<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_credentials', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_password_credentials');
            $table->bigInteger('user_id');
            $table->string('password_hash', 255);
            $table->timestampTz('password_changed_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique('user_id', 'uq_password_credentials_user');
            $table->foreign('user_id', 'fk_password_credentials_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('no action')
                ->onDelete('cascade');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE password_credentials RENAME CONSTRAINT password_credentials_pkey TO pk_password_credentials');
    }

    public function down(): void
    {
        Schema::dropIfExists('password_credentials');
    }
};
