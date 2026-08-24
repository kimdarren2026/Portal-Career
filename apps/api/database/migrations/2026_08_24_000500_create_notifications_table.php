<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_notifications');
            $table->bigInteger('user_id');
            $table->string('type', 64);
            $table->string('title', 255);
            $table->text('body_reference')->nullable();
            $table->string('related_object_type', 64)->nullable();
            $table->bigInteger('related_object_id')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('user_id', 'fk_notifications_user_id')
                ->references('id')->on('users')->onUpdate('no action')->onDelete('restrict');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE notifications RENAME CONSTRAINT notifications_pkey TO pk_notifications');
        DB::statement('CREATE INDEX idx_notifications_user_unread ON notifications (user_id, created_at DESC) WHERE read_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
