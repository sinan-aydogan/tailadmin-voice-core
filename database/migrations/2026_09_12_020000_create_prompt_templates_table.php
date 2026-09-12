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
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable()->default('Genel')->index();
            $table->text('content');
            $table->text('description')->nullable();
            $table->text('system_prompt')->nullable();
            $table->json('variables_schema')->nullable();
            $table->boolean('is_favorite')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
