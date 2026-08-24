<?php

/*
|--------------------------------------------------------------------------
| FRAMEWORK INFRASTRUCTURE MIGRATION — NOT BUSINESS SCHEMA
|--------------------------------------------------------------------------
| Layer:  Laravel framework infrastructure (DATABASE_SCHEMA.md §1, §23)
| Reason: SESSION_DRIVER=database (ADR-006) — sessions live in PostgreSQL so a
|         Redis eviction cannot log out every user simultaneously.
|
| This file deliberately creates ONLY `sessions`. Laravel's stock migration also
| creates `users` and `password_reset_tokens`; both are BUSINESS tables owned by
| logical model 1.1-C3 and belong to MIGRATION_PLAN.md Phase 1. Laravel's
| `password_reset_tokens` shape would additionally collide with the business
| table of the same name and satisfy neither INV-021 nor the frozen model
| (ADR-011), so it must never be created by framework scaffolding.
|
| `sessions.user_id` intentionally carries NO foreign key: the business `users`
| table does not exist until Phase 1, and session rows must not constrain it.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
