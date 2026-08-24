<?php

/*
|--------------------------------------------------------------------------
| FRAMEWORK INFRASTRUCTURE MIGRATION — NOT BUSINESS SCHEMA
|--------------------------------------------------------------------------
| Layer:  Laravel framework infrastructure (DATABASE_SCHEMA.md §1, §23)
| Reason: Laravel writes exhausted queue jobs here regardless of queue driver,
|         and DEPLOYMENT_ARCHITECTURE.md §5 requires `failed_jobs` growth to be
|         monitored and alerted on.
|
| This file deliberately creates ONLY `failed_jobs`. Laravel's stock migration
| also creates `jobs` and `job_batches`; both are omitted because the queue is
| Redis (ADR-006). A database queue table would add polling load and lock
| contention to the same PostgreSQL instance that serves interactive traffic.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};
