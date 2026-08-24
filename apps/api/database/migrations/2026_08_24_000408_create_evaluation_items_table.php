<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_items', function (Blueprint $table) {
            $table->bigInteger('id')->generatedAs()->always();
            $table->primary('id', 'pk_evaluation_items');
            $table->bigInteger('evaluation_id');
            $table->string('criterion', 255);
            $table->decimal('weight', 6, 2)->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->text('comment')->nullable();
            $table->integer('sort_order');
            $table->foreign('evaluation_id', 'fk_evaluation_items_evaluation_id')
                ->references('id')->on('evaluations')->onUpdate('no action')->onDelete('cascade');
        });

        // PostgreSQL grammar ignores Blueprint's primary-key name.
        DB::statement('ALTER TABLE evaluation_items RENAME CONSTRAINT evaluation_items_pkey TO pk_evaluation_items');
        DB::statement('CREATE INDEX idx_evaluation_items_evaluation_id ON evaluation_items (evaluation_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_items');
    }
};
