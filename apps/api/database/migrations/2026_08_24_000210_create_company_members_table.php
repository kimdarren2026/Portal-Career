<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_members', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_company_members');
            $table->bigInteger('company_id');
            $table->bigInteger('user_id');
            $table->string('company_role', 32);
            $table->string('status', 64);
            $table->timestampTz('joined_at');
            $table->bigInteger('invited_by')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreign('company_id', 'fk_company_members_company_id')
                ->references('id')->on('companies')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('user_id', 'fk_company_members_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('invited_by', 'fk_company_members_invited_by')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('set null');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE company_members RENAME CONSTRAINT company_members_pkey TO pk_company_members');
        DB::statement("ALTER TABLE company_members ADD CONSTRAINT chk_company_members_company_role CHECK (company_role IN ('COMPANY_ADMIN', 'COMPANY_RECRUITER'))");
        // INV-017: historical memberships remain, but only one can be active.
        DB::statement('CREATE UNIQUE INDEX uq_company_members_company_user_active ON company_members (company_id, user_id) WHERE revoked_at IS NULL');
        DB::statement('CREATE INDEX idx_company_members_user_id ON company_members (user_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_members');
    }
};
