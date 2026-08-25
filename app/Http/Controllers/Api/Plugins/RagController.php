<?php

namespace App\Http\Controllers\Api\Plugins;

use App\Http\Controllers\Controller;
use App\Services\Rag\Chat2RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class RagController extends Controller
{
    public function __construct(private Chat2RagService $rag)
    {
    }

    public function query(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vector' => ['required', 'array', 'min:1'],
            'vector.*' => ['numeric'],
            'topK' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'includeMetadata' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $this->rag->query(
                array_values(array_map('floatval', $data['vector'])),
                (int) ($data['topK'] ?? 4),
                (bool) ($data['includeMetadata'] ?? true)
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json($result);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vectors' => ['required', 'array', 'min:1'],
            'vectors.*.id' => ['required', 'string'],
            'vectors.*.values' => ['required', 'array', 'min:1'],
            'vectors.*.values.*' => ['numeric'],
            'vectors.*.metadata' => ['sometimes', 'array'],
        ]);

        try {
            $result = $this->rag->upsert($data['vectors']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json($result);
    }

    public function clear(): JsonResponse
    {
        try {
            $result = $this->rag->clear();
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json($result);
    }

    public function fetch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ]);

        try {
            $vectors = $this->rag->fetchIds($data['ids']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['vectors' => $vectors]);
    }

    public function stats(): JsonResponse
    {
        try {
            $stats = $this->rag->stats();
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json($stats);
    }
}
