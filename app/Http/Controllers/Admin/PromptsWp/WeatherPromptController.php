<?php

namespace App\Http\Controllers\Admin\PromptsWp;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\WeatherPromt;
use App\Support\WeatherAiModelChoice;
use App\Support\WeatherCountry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WeatherPromptController extends Controller
{
    public function edit(string $country = WeatherCountry::CH): View
    {
        $country = WeatherCountry::normalize($country);
        $languages = Language::forAdminForms();
        $codes = $languages->pluck('code')->map(fn ($c) => strtolower((string) $c))->values()->all();
        $prefix = WeatherCountry::promptPrefix($country);

        $promptsByCode = [];
        foreach ($codes as $code) {
            $promptsByCode[$code] = WeatherPromt::where('name', $prefix.$code)->value('content') ?? '';
        }

        $defaultCode = strtolower((string) (Language::getDefault()?->code ?? ''));
        if ($defaultCode !== '' && ($promptsByCode[$defaultCode] ?? '') === '') {
            $legacy = WeatherPromt::where('name', WeatherCountry::legacyPromptName($country))->value('content');
            if ($legacy !== null && $legacy !== '') {
                $promptsByCode[$defaultCode] = $legacy;
            }
        }

        $aiModel = WeatherAiModelChoice::normalize(
            WeatherPromt::where('name', WeatherAiModelChoice::SETTING_NAME)->value('content')
        );

        return view('admin.prompts-wp.weather', [
            'pageTitle' => 'Промты — Погода ('.WeatherCountry::label($country).')',
            'country' => $country,
            'countryLabel' => WeatherCountry::label($country),
            'languages' => $languages,
            'promptsByCode' => $promptsByCode,
            'promptLangCodes' => $codes,
            'aiModel' => $aiModel,
            'aiModelChoices' => WeatherAiModelChoice::labels(),
            'saveUrl' => route('admin.prompts-wp.weather.save', $country, false),
            'fieldPrefix' => $prefix,
        ]);
    }

    public function save(string $country, Request $request): JsonResponse
    {
        $country = WeatherCountry::normalize($country);
        $prefix = WeatherCountry::promptPrefix($country);
        $codes = Language::forAdminForms()->pluck('code')->map(fn ($c) => strtolower((string) $c))->values()->all();

        $rules = [
            'weather_ai_model' => ['nullable', 'string', Rule::in(WeatherAiModelChoice::keys())],
        ];
        foreach ($codes as $code) {
            $rules[$prefix.$code] = 'nullable|string';
        }
        $validated = $request->validate($rules);

        $modelKey = WeatherAiModelChoice::normalize($validated['weather_ai_model'] ?? null);
        WeatherPromt::updateOrCreate(
            ['name' => WeatherAiModelChoice::SETTING_NAME],
            ['content' => $modelKey]
        );

        $saved = ['weather_ai_model' => $modelKey];
        foreach ($codes as $code) {
            $key = $prefix.$code;
            $content = $validated[$key] ?? '';
            WeatherPromt::updateOrCreate(
                ['name' => $key],
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
}
