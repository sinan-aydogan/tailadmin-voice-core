<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('trigger_slug')->unique();
            $table->string('trigger_type')->default('webhook'); // 'webhook', 'manual'
            $table->json('definition'); // { nodes: [...], edges: [...] }
            $table->timestamps();
        });

        Schema::create('flow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index(); // pending, running, completed, failed
            $table->json('trigger_payload')->nullable();
            $table->json('context')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('flow_run_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_run_id')->constrained()->cascadeOnDelete();
            $table->string('node_id');
            $table->string('node_type');
            $table->string('status')->default('completed'); // completed, failed, skipped
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flow_run_logs');
        Schema::dropIfExists('flow_runs');
        Schema::dropIfExists('flows');
    }
};
