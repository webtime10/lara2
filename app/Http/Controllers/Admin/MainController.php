<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GeminiKeyProbeService;
use App\Support\GeminiApiKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MainController extends Controller
{
    public function index(): View
    {
        $keys = GeminiApiKeys::fromConfig();
        $maskedKeys = array_map(
            fn (string $key, int $i) => ['index' => $i + 1, 'mask' => GeminiApiKeys::mask($key)],
            $keys,
            array_keys($keys)
        );

        return view('admin.index', [
            'pageTitle' => 'Admin Panel',
            'geminiKeyCount' => count($keys),
            'geminiMaskedKeys' => $maskedKeys,
            'geminiModel' => config('services.gemini.model', 'gemini-2.5-flash'),
        ]);
    }

    public function testAllGeminiKeys(GeminiKeyProbeService $probe): JsonResponse
    {
        $keys = GeminiApiKeys::fromConfig();
        if ($keys === []) {
            return response()->json([
                'success' => false,
                'message' => 'GEMINI_API_KEY пуст в .env',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Проверено ключей: '.count($keys),
            'results' => $probe->probeAllConfigured(),
        ]);
    }

    public function testNextGeminiKey(GeminiKeyProbeService $probe): JsonResponse
    {
        $keys = GeminiApiKeys::fromConfig();
        if ($keys === []) {
            return response()->json([
                'success' => false,
                'message' => 'GEMINI_API_KEY пуст в .env',
            ], 400);
        }

        $key = GeminiApiKeys::nextRoundRobin();
        $position = GeminiApiKeys::roundRobinPosition();

        $result = $probe->probeKey($key, $position);
        $result['round_robin'] = $position.' / '.count($keys);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['ok']
                ? 'Ключ #'.$position.' ('.$result['mask'].') — OK'
                : 'Ключ #'.$position.' ('.$result['mask'].') — '.$result['message'],
            'result' => $result,
        ]);
    }

    public function resetGeminiKeyRotation(): JsonResponse
    {
        GeminiApiKeys::resetRoundRobin();

        return response()->json([
            'success' => true,
            'message' => 'Счётчик round-robin сброшен. Следующий запрос возьмёт ключ №1.',
        ]);
    }
}
