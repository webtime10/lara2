<?php

namespace App\Http\Controllers\Admin\PromptsWp;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\WeatherPromt;
use App\Support\WeatherAiModelChoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WeatherPromptController extends Controller
{
    public function edit(): View
    {
        $languages = Language::forAdminForms();
        $codes = $languages->pluck('code')->map(fn ($c) => strtolower((string) $c))->values()->all();

        $promptsByCode = [];
        foreach ($codes as $code) {
            $promptsByCode[$code] = WeatherPromt::where('name', $this->promptName($code))->value('content') ?? '';
        }

        $defaultCode = strtolower((string) (Language::getDefault()?->code ?? ''));
        if ($defaultCode !== '' && ($promptsByCode[$defaultCode] ?? '') === '') {
            $legacy = WeatherPromt::where('name', 'glavnyy_prompt')->value('content');
            if ($legacy !== null && $legacy !== '') {
                $promptsByCode[$defaultCode] = $legacy;
            }
        }

        $aiModel = WeatherAiModelChoice::normalize(
            WeatherPromt::where('name', WeatherAiModelChoice::SETTING_NAME)->value('content')
        );

        return view('admin.prompts-wp.weather', [
            'pageTitle' => 'Промты WP — Weather',
            'languages' => $languages,
            'promptsByCode' => $promptsByCode,
            'promptLangCodes' => $codes,
            'aiModel' => $aiModel,
            'aiModelChoices' => WeatherAiModelChoice::labels(),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $codes = Language::forAdminForms()->pluck('code')->map(fn ($c) => strtolower((string) $c))->values()->all();

        $rules = [
            'weather_ai_model' => ['nullable', 'string', Rule::in(WeatherAiModelChoice::keys())],
        ];
        foreach ($codes as $code) {
            $rules['glavnyy_prompt_'.$code] = 'nullable|string';
        }
        $validated = $request->validate($rules);

        $modelKey = WeatherAiModelChoice::normalize($validated['weather_ai_model'] ?? null);
        WeatherPromt::updateOrCreate(
            ['name' => WeatherAiModelChoice::SETTING_NAME],
            ['content' => $modelKey]
        );

        $saved = ['weather_ai_model' => $modelKey];
        foreach ($codes as $code) {
            $key = 'glavnyy_prompt_'.$code;
            $content = $validated[$key] ?? '';
            WeatherPromt::updateOrCreate(
                ['name' => $this->promptName($code)],
                ['content' => $content]
            );
            $saved[$key] = $content;
        }

        return response()->json([
            'success' => true,
            'message' => 'Промт и модель сохранены',
            'prompts' => $saved,
        ]);
    }

    private function promptName(string $code): string
    {
        return 'glavnyy_prompt_'.$code;
    }
}
