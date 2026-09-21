<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('flow_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_id');
            $table->json('messages')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['flow_id', 'conversation_id']);
        });

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content');
            $table->string('source_filename')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('content');
            $table->timestamps();
        });

        // Standalone FTS5 virtual table, kept in sync explicitly from PHP
        // (KnowledgeService) rather than via SQLite triggers, so indexing
        // stays visible and debuggable from the Laravel side.
        DB::statement('CREATE VIRTUAL TABLE IF NOT EXISTS knowledge_chunks_fts USING fts5(content)');

        // No flow-level document association table: each action.llm node
        // picks its own document set (stored as knowledge_document_ids in
        // the node's own JSON config inside flows.definition), so multiple
        // LLM nodes in the same flow can each draw on different documents.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS knowledge_chunks_fts');
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_documents');
        Schema::dropIfExists('flow_sessions');
    }
};
