<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_apply_events', function (Blueprint $table) {
            $table->foreign('consent_id', 'fk_external_apply_events_consent_id')
                ->references('id')->on('consents')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('external_apply_events', function (Blueprint $table) {
            $table->dropForeign('fk_external_apply_events_consent_id');
        });
    }
};
