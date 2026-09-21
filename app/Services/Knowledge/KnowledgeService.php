<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\DB;

class KnowledgeService
{
    protected const CHUNK_SIZE = 800;
    protected const CHUNK_OVERLAP = 100;

    /**
     * Store a document, split it into overlapping chunks, and index each
     * chunk into the knowledge_chunks_fts FTS5 virtual table.
     */
    public function addDocument(string $title, string $content, ?string $sourceFilename = null): KnowledgeDocument
    {
        $document = KnowledgeDocument::create([
            'title' => $title,
            'content' => $content,
            'source_filename' => $sourceFilename,
        ]);

        foreach ($this->splitIntoChunks($content) as $index => $chunkText) {
            $chunk = KnowledgeChunk::create([
                'knowledge_document_id' => $document->id,
                'chunk_index' => $index,
                'content' => $chunkText,
            ]);

            DB::statement('INSERT INTO knowledge_chunks_fts(rowid, content) VALUES (?, ?)', [$chunk->id, $chunkText]);
        }

        return $document;
    }

    /**
     * Remove a document along with its chunks and their FTS5 index rows.
     */
    public function deleteDocument(KnowledgeDocument $document): void
    {
        $chunkIds = $document->chunks()->pluck('id');

        foreach ($chunkIds as $chunkId) {
            DB::statement('DELETE FROM knowledge_chunks_fts WHERE rowid = ?', [$chunkId]);
        }

        $document->chunks()->delete();
        $document->delete();
    }

    /**
     * Full-text search across the given documents' chunks. Returns the
     * top-$limit most relevant chunks with their parent document title.
     *
     * @param array<int> $documentIds
     * @return array<array{document_title:string, content:string}>
     */
    public function search(array $documentIds, string $query, int $limit = 4): array
    {
        if (empty($documentIds)) {
            return [];
        }

        $matchQuery = $this->buildMatchQuery($query);
        if ($matchQuery === '') {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($documentIds), '?'));

        $rows = DB::select(
            "SELECT kc.content as content, kd.title as document_title
             FROM knowledge_chunks_fts
             JOIN knowledge_chunks kc ON kc.id = knowledge_chunks_fts.rowid
             JOIN knowledge_documents kd ON kd.id = kc.knowledge_document_id
             WHERE knowledge_chunks_fts MATCH ?
               AND kc.knowledge_document_id IN ({$placeholders})
             ORDER BY rank
             LIMIT ?",
            [$matchQuery, ...$documentIds, $limit]
        );

        return array_map(fn ($row) => [
            'document_title' => $row->document_title,
            'content' => $row->content,
        ], $rows);
    }

    /**
     * Build a permissive "word1 OR word2 OR ..." FTS5 MATCH expression from
     * a free-form question, since exact-phrase matching is too fragile for
     * natural language queries.
     */
    protected function buildMatchQuery(string $query): string
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $query, $matches);
        $words = array_filter($matches[0] ?? [], fn ($w) => mb_strlen($w) >= 2);
        $words = array_slice(array_unique($words), 0, 12);

        if (empty($words)) {
            return '';
        }

        $escaped = array_map(fn ($w) => '"' . str_replace('"', '""', $w) . '"', $words);

        return implode(' OR ', $escaped);
    }

    /**
     * Split text into overlapping chunks, breaking on whitespace boundaries
     * where possible instead of mid-word.
     *
     * @return array<string>
     */
    protected function splitIntoChunks(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $length = mb_strlen($content);
        if ($length <= self::CHUNK_SIZE) {
            return [$content];
        }

        $chunks = [];
        $start = 0;

        while ($start < $length) {
            $end = min($start + self::CHUNK_SIZE, $length);

            if ($end < $length) {
                $lastSpace = mb_strrpos(mb_substr($content, $start, $end - $start), ' ');
                if ($lastSpace !== false && $lastSpace > self::CHUNK_SIZE * 0.5) {
                    $end = $start + $lastSpace;
                }
            }

            $chunks[] = trim(mb_substr($content, $start, $end - $start));

            if ($end >= $length) {
                break;
            }

            $start = max($end - self::CHUNK_OVERLAP, $start + 1);
        }

        return array_values(array_filter($chunks, fn ($c) => $c !== ''));
    }
}
