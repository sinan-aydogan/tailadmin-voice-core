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
        Schema::create('voice_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // 'tts', 'stt'
            $table->string('status')->default('pending')->index(); // 'pending', 'running', 'completed', 'failed', 'cancelled'
            $table->json('payload');
            $table->json('result')->nullable();
            $table->string('output_path')->nullable();
            $table->integer('progress')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('model_downloads', function (Blueprint $table) {
            $table->id();
            $table->string('model_id')->unique();
            $table->string('status')->default('pending')->index(); // 'pending', 'downloading', 'completed', 'failed'
            $table->float('progress')->default(0.0);
            $table->unsignedBigInteger('downloaded_bytes')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('voice_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sample_path')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('voice_playlists', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('items')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_playlists');
        Schema::dropIfExists('voice_profiles');
        Schema::dropIfExists('model_downloads');
        Schema::dropIfExists('voice_tasks');
    }
};
