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
        Schema::table('voice_playlists', function (Blueprint $table) {
            $table->string('engine')->default('piper-tr')->after('description');
            $table->string('language')->default('tr')->after('engine');
            $table->unsignedBigInteger('profile_id')->nullable()->after('language');
            $table->string('status')->default('pending')->after('profile_id'); // 'pending', 'processing', 'completed', 'failed'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voice_playlists', function (Blueprint $table) {
            $table->dropColumn(['engine', 'language', 'profile_id', 'status']);
        });
    }
};
