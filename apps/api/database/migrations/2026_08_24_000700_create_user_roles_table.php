<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_user_roles');
            $table->bigInteger('user_id');
            $table->bigInteger('role_id');
            $table->timestampTz('assigned_at');
            $table->bigInteger('assigned_by')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->bigInteger('revoked_by')->nullable();

            $table->foreign('user_id', 'fk_user_roles_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('role_id', 'fk_user_roles_role_id')
                ->references('id')->on('roles')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('assigned_by', 'fk_user_roles_assigned_by')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign('revoked_by', 'fk_user_roles_revoked_by')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE user_roles RENAME CONSTRAINT user_roles_pkey TO pk_user_roles');
        DB::statement('CREATE UNIQUE INDEX uq_user_roles_user_role_active ON user_roles (user_id, role_id) WHERE revoked_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
