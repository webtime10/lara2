<?php

namespace App\Http\Controllers\Api\Plugins;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Plugins\IdealRegionIncomingRequest;
use App\Services\Plugins\IdealRegion\IdealRegionMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * API-плагин «Ideal Region» (Ваш идеальный регион).
 *
 * Точка входа для WordPress-квиза: POST /api/plugins/ideal_region
 *
 * Цепочка обработки запроса:
 *   1. routes/api.php — маршрут с middleware plugin.api:ideal_region
 *   2. VerifyPluginApiKey — проверка X-Plugin-Api-Key (до контроллера)
 *   3. IdealRegionIncomingRequest — валидация JSON-тела
 *   4. IdealRegionController::store() — этот файл
 *   5. IdealRegionMatchService::match() — расчёт и подбор регионов
 *
 * Входящий JSON (пример):
 * {
 *   "language": "en",
 *   "session_token": "abc123",
 *   "manufacturer_id": 1,
 *   "answers": {
 *     "catalog": {
 *       "step_1": { "slot": 1 },
 *       "step_2": { "slot": 3 },
 *       "step_3": { "slot": 2 }
 *     }
 *   }
 * }
 *
 * slot — номер выбранного варианта на шаге (1, 2, 3…).
 * Слоты шагов: config/ideal_region_category_fields.php → step_slots
 * (step1: vesnoy/letom/…, step5: vysokogornye_alpy/… и т.д.).
 */
class IdealRegionController extends Controller
{
    public function __construct(
        /** Сервис подбора регионов по ответам квиза и оценкам из category_descriptions. */
        private IdealRegionMatchService $matchService,
    ) {}

    /**
     * Принимает ответы квиза «Ваш идеальный регион» и возвращает подобранные регионы.
     *
     * Вызывается WordPress-плагином после прохождения всех шагов квиза.
     * Расчёт синхронный (в отличие от budget, где используется очередь).
     */
    public function store(IdealRegionIncomingRequest $request): JsonResponse
    {
        // IdealRegionIncomingRequest уже проверил:
        //   - language (обязательно)
        //   - session_token (необязательно)
        //   - manufacturer_id (необязательно)
        //   - answers.catalog (обязательный массив с выборами step_1 … step_8)
        $payload = $request->validated();

        // Основной расчёт: сравниваем выбор пользователя с оценками категорий (Швейцария)
        // из таблицы category_descriptions (step1_vesnoy, step5_vysokogornye_alpy и т.д.).
        // Возвращает top_regions, best_match, match_score, step1_score и др.
        // Логика: app/Services/Plugins/IdealRegion/IdealRegionMatchService.php
        $result = $this->matchService->match($payload);

        // Логируем факт запроса — для отладки и мониторинга (storage/logs/laravel.log).
        Log::info('ideal_region.incoming', [
            'language' => $payload['language'] ?? null,
            'session_token' => $payload['session_token'] ?? null,
            'catalog_keys' => array_keys($payload['answers']['catalog'] ?? []),
            'manufacturer_id' => $result['manufacturer_id'] ?? null,
            'manufacturer_name' => $result['manufacturer_name'] ?? null,
            'regions_count' => count($result['regions'] ?? []),
            'top_regions_count' => count($result['top_regions'] ?? []),
            'best_match' => $result['best_match']['name'] ?? null,
            'error' => $result['error'] ?? null,
        ]);

        // Ответ WordPress: ok + эхо входящих данных + результат подбора.
        return response()->json([
            'ok' => true,
            'plugin' => 'ideal_region',
            'status' => 'matched',
            'message' => 'Ideal region answers processed.',

            // Эхо того, что пришло от WP (удобно для отладки на стороне плагина).
            'received' => $payload,

            // Схема слотов шагов: step1 → [vesnoy, letom, …] и т.д.
            // WP может использовать для отображения подписей вариантов.
            'step_slots' => $this->matchService->stepSlots(),

            // Главный результат:
            //   result.top_regions / matched_names — топ-3 региона
            //   result.best_match — лучший регион
            //   result.regions — все регионы с оценками (отсортированы)
            'result' => $result,
        ], 200);
    }
}
