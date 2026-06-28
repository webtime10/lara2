<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseConnectionProbeService;
use App\Services\DataForSeoClient;
use App\Services\DataForSeoProbeService;
use App\Services\GeminiKeyProbeService;
use App\Services\OpenAiService;
use App\Services\OpenAiKeyProbeService;
use App\Support\GeminiApiKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MainController extends Controller
{
    public function index(DataForSeoClient $dataForSeoClient, OpenAiService $openAi): View
    {
        $keys = GeminiApiKeys::fromConfig();
        $proKeys = GeminiApiKeys::fromConfig('services.gemini_pro.key');
        $openAiKeys = $openAi->collectApiKeys();
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
            'geminiProKeyConfigured' => $proKeys !== [],
            'geminiProKeyMask' => $proKeys !== [] ? GeminiApiKeys::mask($proKeys[0]) : null,
            'geminiProModel' => config('services.gemini_pro.model', 'gemini-2.5-pro'),
            'openAiKeyConfigured' => $openAiKeys !== [],
            'openAiKeyMask' => $openAiKeys !== [] ? GeminiApiKeys::mask($openAiKeys[0]) : null,
            'openAiKeyCount' => count($openAiKeys),
            'openAiModel' => config('services.openai.model', 'gpt-4o-mini'),
            'openAiExtractionModel' => config('services.openai.extraction_model', 'gpt-4o-mini'),
            'dataForSeoConfigured' => $dataForSeoClient->credentialsConfigured(),
            'dataForSeoLoginMasked' => $dataForSeoClient->maskLogin(),
            'defaultDbConnection' => config('database.default'),
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

    public function testGeminiProKey(GeminiKeyProbeService $probe): JsonResponse
    {
        $result = $probe->probeConfiguredProKey();

        return response()->json([
            'success' => (bool) $result['ok'],
            'message' => $result['ok']
                ? 'GEMINI_PRO_API_KEY — OK'
                : 'GEMINI_PRO_API_KEY — '.$result['message'],
            'result' => $result,
        ], $result['ok'] ? 200 : 422);
    }

    public function testOpenAiKey(OpenAiKeyProbeService $probe): JsonResponse
    {
        $result = $probe->probeConfiguredKey();

        return response()->json([
            'success' => (bool) $result['ok'],
            'message' => $result['ok']
                ? 'OPENAI_API_KEY — OK'
                : 'OPENAI_API_KEY — '.$result['message'],
            'result' => $result,
        ], $result['ok'] ? 200 : 422);
    }

    public function testDataForSeo(DataForSeoProbeService $probe): JsonResponse
    {
        $result = $probe->probe();

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'result' => $result,
        ], $result['ok'] ? 200 : 422);
    }

    public function testDatabases(DatabaseConnectionProbeService $probe): JsonResponse
    {
        $results = $probe->probeAll();
        $allOk = collect($results)->every(fn (array $row) => $row['ok']);

        return response()->json([
            'success' => $allOk,
            'message' => $allOk
                ? 'MySQL и PostgreSQL доступны'
                : 'Есть проблемы с подключением к базам данных',
            'default_connection' => config('database.default'),
            'results' => $results,
        ], $allOk ? 200 : 422);
    }
}
