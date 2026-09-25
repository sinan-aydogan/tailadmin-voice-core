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
        Schema::create('story_projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('theme')->nullable();
            $table->string('target_audience')->nullable()->default('general');
            $table->json('options')->nullable();
            $table->json('script_data')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('master_audio_path')->nullable();
            $table->json('stems')->nullable();
            $table->float('duration_sec')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('story_projects');
    }
};
