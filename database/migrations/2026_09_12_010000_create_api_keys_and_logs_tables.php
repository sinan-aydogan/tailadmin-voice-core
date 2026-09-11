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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique()->index();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('requests_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->string('action_type')->index();
            $table->string('endpoint');
            $table->string('method', 10)->default('GET');
            $table->string('ip_address', 45)->nullable();
            $table->integer('status_code')->default(200);
            $table->foreignId('task_id')->nullable()->constrained('voice_tasks')->nullOnDelete();
            $table->json('request_summary')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('api_keys');
    }
};
