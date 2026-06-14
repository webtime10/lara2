<?php

namespace App\Http\Controllers\Admin\PromptsWp;

use App\Http\Controllers\Controller;
use App\Models\BudgetPromt;
use App\Models\Language;
use App\Support\BudgetAiModelChoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetPromptController extends Controller
{
    public function edit(): View
    {
        $languages = Language::forAdminForms();
        $codes = $languages->pluck('code')->map(fn ($c) => strtolower((string) $c))->values()->all();

        $promptsByCode = [];
        foreach ($codes as $code) {
            $promptsByCode[$code] = BudgetPromt::where('name', $this->promptName($code))->value('content') ?? '';
        }

        $defaultCode = strtolower((string) (Language::getDefault()?->code ?? ''));
        if ($defaultCode !== '' && ($promptsByCode[$defaultCode] ?? '') === '') {
            $legacy = BudgetPromt::where('name', 'glavnyy_prompt')->value('content');
            if ($legacy !== null && $legacy !== '') {
                $promptsByCode[$defaultCode] = $legacy;
            }
        }

        $aiModel = BudgetAiModelChoice::normalize(
            BudgetPromt::where('name', BudgetAiModelChoice::SETTING_NAME)->value('content')
        );

        return view('admin.prompts-wp.budget', [
            'pageTitle' => 'Промты WP — Budget',
            'languages' => $languages,
            'promptsByCode' => $promptsByCode,
            'promptLangCodes' => $codes,
            'aiModel' => $aiModel,
            'aiModelChoices' => BudgetAiModelChoice::labels(),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $codes = Language::forAdminForms()->pluck('code')->map(fn ($c) => strtolower((string) $c))->values()->all();

        $rules = [
            'budget_ai_model' => ['nullable', 'string', Rule::in(BudgetAiModelChoice::keys())],
        ];
        foreach ($codes as $code) {
            $rules['glavnyy_prompt_'.$code] = 'nullable|string';
        }
        $validated = $request->validate($rules);

        $modelKey = BudgetAiModelChoice::normalize($validated['budget_ai_model'] ?? null);
        BudgetPromt::updateOrCreate(
            ['name' => BudgetAiModelChoice::SETTING_NAME],
            ['content' => $modelKey]
        );

        $saved = ['budget_ai_model' => $modelKey];
        foreach ($codes as $code) {
            $key = 'glavnyy_prompt_'.$code;
            $content = $validated[$key] ?? '';
            BudgetPromt::updateOrCreate(
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
