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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('system_prompt')->nullable();

            // LLM provider overrides — null falls back to global Settings.
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->text('api_key')->nullable();
            $table->string('base_url')->nullable();

            // Tools: [{name, description, flow_id, parameters (JSON Schema)}]
            $table->json('tools')->nullable();
            $table->unsignedTinyInteger('max_tool_iterations')->default(6);

            // RAG (same shape as a Flow's action.llm node config).
            $table->boolean('use_knowledge')->default(false);
            $table->json('knowledge_document_ids')->nullable();

            // Voice I/O.
            $table->boolean('stt_enabled')->default(true);
            $table->string('stt_language')->default('tr');
            $table->string('stt_model_size')->nullable();
            $table->string('tts_engine')->default('piper-tr');
            $table->string('tts_language')->default('tr');
            $table->unsignedBigInteger('tts_profile_id')->nullable();
            $table->string('response_mode')->default('audio'); // json|text|audio|twiml
            $table->string('twiml_type')->nullable();

            $table->boolean('is_active')->default(true);
            $table->string('trigger_slug')->unique();
            $table->string('trigger_type')->default('webhook');
            $table->timestamps();
        });

        Schema::create('agent_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_id');
            $table->json('messages')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'conversation_id']);
        });

        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_id')->nullable();
            $table->string('status')->default('pending')->index(); // pending, running, completed, failed
            $table->json('trigger_payload')->nullable();
            $table->text('final_reply')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
            $table->string('step_type'); // llm_call, tool_call
            $table->string('tool_name')->nullable();
            $table->foreignId('flow_id')->nullable()->constrained()->nullOnDelete();
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
        Schema::dropIfExists('agent_run_steps');
        Schema::dropIfExists('agent_runs');
        Schema::dropIfExists('agent_sessions');
        Schema::dropIfExists('agents');
    }
};
