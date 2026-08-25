<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\DB;
use Throwable;

class Chat2RagService
{
    private const CHUNK_ID_PATTERN = '/^page_(\d+)_chunk_(\d+)$/';

    /**
     * @param  list<float>  $vector
     * @return array{matches: list<array<string, mixed>>}
     */
    public function query(array $vector, int $topK = 4, bool $includeMetadata = true): array
    {
        $topK = max(1, min(50, $topK));
        $literal = $this->vectorLiteral($vector);

        $rows = DB::connection('pgsql')->select(
            <<<SQL
            SELECT
                c.id AS chunk_id,
                c.chunk_index,
                c.chunk_text,
                p.post_id,
                p.title,
                p.url,
                1 - (c.embedding <=> ?::vector) AS score
            FROM sw_chat2_chunks c
            INNER JOIN sw_chat2_pages p ON p.id = c.page_id
            ORDER BY c.embedding <=> ?::vector
            LIMIT ?
            SQL,
            [$literal, $literal, $topK]
        );

        $matches = [];
        foreach ($rows as $row) {
            $postId = (int) $row->post_id;
            $chunkIndex = (int) $row->chunk_index;
            $match = [
                'id' => 'page_'.$postId.'_chunk_'.$chunkIndex,
                'score' => (float) $row->score,
            ];

            if ($includeMetadata) {
                $match['metadata'] = [
                    'post_id' => $postId,
                    'title' => (string) $row->title,
                    'url' => (string) $row->url,
                    'chunk_text' => (string) $row->chunk_text,
                ];
            }

            $matches[] = $match;
        }

        return ['matches' => $matches];
    }

    /**
     * @param  list<array{id: string, values: list<float>, metadata?: array<string, mixed>}>  $vectors
     */
    public function upsert(array $vectors): array
    {
        if ($vectors === []) {
            return ['upsertedCount' => 0];
        }

        $grouped = [];
        foreach ($vectors as $vector) {
            $parsed = $this->parseVectorRow($vector);
            if ($parsed === null) {
                continue;
            }
            $grouped[$parsed['post_id']][] = $parsed;
        }

        $upserted = 0;

        DB::connection('pgsql')->transaction(function () use ($grouped, &$upserted): void {
            foreach ($grouped as $postId => $items) {
                $first = $items[0];
                $hashSource = implode("\n", array_map(static fn (array $item): string => $item['chunk_text'], $items));

                DB::connection('pgsql')->table('sw_chat2_pages')->updateOrInsert(
                    ['post_id' => $postId],
                    [
                        'title' => $first['title'],
                        'url' => $first['url'],
                        'content_hash' => md5($hashSource),
                        'updated_at' => now(),
                    ]
                );

                $pageId = (int) DB::connection('pgsql')->table('sw_chat2_pages')
                    ->where('post_id', $postId)
                    ->value('id');

                DB::connection('pgsql')->table('sw_chat2_chunks')->where('page_id', $pageId)->delete();

                foreach ($items as $item) {
                    DB::connection('pgsql')->insert(
                        'INSERT INTO sw_chat2_chunks (page_id, chunk_index, chunk_text, embedding, created_at)
                         VALUES (?, ?, ?, ?::vector, ?)',
                        [
                            $pageId,
                            $item['chunk_index'],
                            $item['chunk_text'],
                            $this->vectorLiteral($item['values']),
                            now(),
                        ]
                    );
                    ++$upserted;
                }
            }
        });

        return ['upsertedCount' => $upserted];
    }

    public function clear(): array
    {
        DB::connection('pgsql')->transaction(function (): void {
            DB::connection('pgsql')->table('sw_chat2_chunks')->delete();
            DB::connection('pgsql')->table('sw_chat2_pages')->delete();
        });

        return ['ok' => true];
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array<string, mixed>>
     */
    public function fetchIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('strval', $ids), static fn (string $id): bool => $id !== ''));
        if ($ids === []) {
            return [];
        }

        $found = [];
        foreach ($ids as $id) {
            if (! preg_match(self::CHUNK_ID_PATTERN, $id, $m)) {
                continue;
            }

            $postId = (int) $m[1];
            $chunkIndex = (int) $m[2];

            $exists = DB::connection('pgsql')->table('sw_chat2_chunks as c')
                ->join('sw_chat2_pages as p', 'p.id', '=', 'c.page_id')
                ->where('p.post_id', $postId)
                ->where('c.chunk_index', $chunkIndex)
                ->exists();

            if ($exists) {
                $found[$id] = ['id' => $id];
            }
        }

        return $found;
    }

    /**
     * @return array{pages: int, chunks: int}
     */
    public function stats(): array
    {
        return [
            'pages' => (int) DB::connection('pgsql')->table('sw_chat2_pages')->count(),
            'chunks' => (int) DB::connection('pgsql')->table('sw_chat2_chunks')->count(),
        ];
    }

    /**
     * @param  list<float>  $vector
     */
    private function vectorLiteral(array $vector): string
    {
        $parts = array_map(static fn ($v): string => is_numeric($v) ? (string) (float) $v : '0', $vector);

        return '['.implode(',', $parts).']';
    }

    /**
     * @param  array{id: string, values: list<float>, metadata?: array<string, mixed>}  $vector
     * @return array{
     *   post_id: int,
     *   chunk_index: int,
     *   chunk_text: string,
     *   title: string,
     *   url: string,
     *   values: list<float>
     * }|null
     */
    private function parseVectorRow(array $vector): ?array
    {
        $id = isset($vector['id']) ? (string) $vector['id'] : '';
        $values = isset($vector['values']) && is_array($vector['values']) ? $vector['values'] : [];
        $meta = isset($vector['metadata']) && is_array($vector['metadata']) ? $vector['metadata'] : [];

        if ($id === '' || $values === []) {
            return null;
        }

        $postId = isset($meta['post_id']) ? (int) $meta['post_id'] : 0;
        $chunkIndex = 0;

        if (preg_match(self::CHUNK_ID_PATTERN, $id, $m)) {
            $postId = $postId > 0 ? $postId : (int) $m[1];
            $chunkIndex = (int) $m[2];
        }

        if ($postId <= 0) {
            return null;
        }

        $chunkText = '';
        foreach (['chunk_text', 'text', 'content', 'chunk'] as $key) {
            if (isset($meta[$key]) && is_string($meta[$key]) && trim($meta[$key]) !== '') {
                $chunkText = trim($meta[$key]);
                break;
            }
        }

        if ($chunkText === '') {
            return null;
        }

        return [
            'post_id' => $postId,
            'chunk_index' => $chunkIndex,
            'chunk_text' => $chunkText,
            'title' => isset($meta['title']) ? (string) $meta['title'] : '',
            'url' => isset($meta['url']) ? (string) $meta['url'] : '',
            'values' => array_values(array_map('floatval', $values)),
        ];
    }
}
