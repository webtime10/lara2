<?php

namespace App\Http\Controllers\Admin\PromptsWp;

use App\Http\Controllers\Controller;
use App\Models\BudgetPromt;
use App\Models\Language;
use App\Services\BudgetPriorityAdjustmentService;
use App\Support\BudgetAiModelChoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetPromptController extends Controller
{
    private const ENTERTAINMENT_PROMPT_NAME = 'entertainment_prompt';

    private const ENTERTAINMENT_PROMPT_DAILY_NAME = 'entertainment_prompt_daily';

    private const ENTERTAINMENT_PROMPT_EVERY_TWO_DAYS_NAME = 'entertainment_prompt_every_two_days';

    private const ENTERTAINMENT_PROMPT_EVERY_THREE_DAYS_NAME = 'entertainment_prompt_every_three_days';

    private const CAFE_PROMPT_NAME = 'cafe_prompt';

    private const RESTAURANTS_PROMPT_NAME = 'restaurants_prompt';

	private const GROCERY_PROMPT_NAME = 'korzina_magazina';

    private const CAR_ECONOMY_PROMPT_NAME = 'car_economy_prompt';

    private const CAR_MEDIUM_PROMPT_NAME = 'car_medium_prompt';

    private const CAR_LUXURY_PROMPT_NAME = 'car_luxury_prompt';

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
            'entertainmentPrompt' => BudgetPromt::where('name', self::ENTERTAINMENT_PROMPT_NAME)->value('content') ?? '',
            'entertainmentPromptDaily' => $this->entertainmentPromptContent(self::ENTERTAINMENT_PROMPT_DAILY_NAME),
            'entertainmentPromptEveryTwoDays' => $this->entertainmentPromptContent(self::ENTERTAINMENT_PROMPT_EVERY_TWO_DAYS_NAME),
            'entertainmentPromptEveryThreeDays' => $this->entertainmentPromptContent(self::ENTERTAINMENT_PROMPT_EVERY_THREE_DAYS_NAME),
			'groceryPrompt' => BudgetPromt::where('name', self::GROCERY_PROMPT_NAME)->value('content') ?? '',
            'cafePrompt' => BudgetPromt::where('name', self::CAFE_PROMPT_NAME)->value('content') ?? '',
            'restaurantsPrompt' => BudgetPromt::where('name', self::RESTAURANTS_PROMPT_NAME)->value('content') ?? '',
            'carEconomyPrompt' => BudgetPromt::where('name', self::CAR_ECONOMY_PROMPT_NAME)->value('content') ?? '',
            'carMediumPrompt' => BudgetPromt::where('name', self::CAR_MEDIUM_PROMPT_NAME)->value('content') ?? '',
            'carLuxuryPrompt' => BudgetPromt::where('name', self::CAR_LUXURY_PROMPT_NAME)->value('content') ?? '',
            'budgetPriorityStrictPercent' => $this->settingValue(BudgetPriorityAdjustmentService::STRICT_PROMPT),
            'budgetPriorityBalancePercent' => $this->settingValue(BudgetPriorityAdjustmentService::BALANCE_PROMPT),
            'budgetPriorityRelaxPercent' => $this->settingValue(BudgetPriorityAdjustmentService::RELAX_PROMPT),
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
            'entertainment_prompt' => ['nullable', 'string'],
            'entertainment_prompt_daily' => ['nullable', 'string'],
            'entertainment_prompt_every_two_days' => ['nullable', 'string'],
            'entertainment_prompt_every_three_days' => ['nullable', 'string'],
			'korzina_magazina' => ['nullable', 'string'],
            'cafe_prompt' => ['nullable', 'string'],
            'restaurants_prompt' => ['nullable', 'string'],
            'car_economy_prompt' => ['nullable', 'string'],
            'car_medium_prompt' => ['nullable', 'string'],
            'car_luxury_prompt' => ['nullable', 'string'],
            'budget_priority_strict_percent' => ['nullable', 'string', 'max:32'],
            'budget_priority_balance_percent' => ['nullable', 'string', 'max:32'],
            'budget_priority_relax_percent' => ['nullable', 'string', 'max:32'],
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
        if (array_key_exists('entertainment_prompt', $validated)) {
            $entertainmentPrompt = $validated['entertainment_prompt'] ?? '';
            BudgetPromt::updateOrCreate(
                ['name' => self::ENTERTAINMENT_PROMPT_NAME],
                ['content' => $entertainmentPrompt]
            );
            $saved['entertainment_prompt'] = $entertainmentPrompt;
        }

        $entertainmentPromptDaily = $validated['entertainment_prompt_daily'] ?? '';
        BudgetPromt::updateOrCreate(
            ['name' => self::ENTERTAINMENT_PROMPT_DAILY_NAME],
            ['content' => $entertainmentPromptDaily]
        );
        $saved['entertainment_prompt_daily'] = $entertainmentPromptDaily;

        $entertainmentPromptEveryTwoDays = $validated['entertainment_prompt_every_two_days'] ?? '';
        BudgetPromt::updateOrCreate(
            ['name' => self::ENTERTAINMENT_PROMPT_EVERY_TWO_DAYS_NAME],
            ['content' => $entertainmentPromptEveryTwoDays]
        );
        $saved['entertainment_prompt_every_two_days'] = $entertainmentPromptEveryTwoDays;

        $entertainmentPromptEveryThreeDays = $validated['entertainment_prompt_every_three_days'] ?? '';
        BudgetPromt::updateOrCreate(
            ['name' => self::ENTERTAINMENT_PROMPT_EVERY_THREE_DAYS_NAME],
            ['content' => $entertainmentPromptEveryThreeDays]
        );
        $saved['entertainment_prompt_every_three_days'] = $entertainmentPromptEveryThreeDays;

		$groceryPrompt = $validated['korzina_magazina'] ?? '';
		BudgetPromt::updateOrCreate(
			['name' => self::GROCERY_PROMPT_NAME],
			['content' => $groceryPrompt]
		);
		$saved['korzina_magazina'] = $groceryPrompt;

        $cafePrompt = $validated['cafe_prompt'] ?? '';
        BudgetPromt::updateOrCreate(
            ['name' => self::CAFE_PROMPT_NAME],
            ['content' => $cafePrompt]
        );
        $saved['cafe_prompt'] = $cafePrompt;

        $restaurantsPrompt = $validated['restaurants_prompt'] ?? '';
        BudgetPromt::updateOrCreate(
            ['name' => self::RESTAURANTS_PROMPT_NAME],
            ['content' => $restaurantsPrompt]
        );
        $saved['restaurants_prompt'] = $restaurantsPrompt;

        $carEconomyPrompt = $validated['car_economy_prompt'] ?? '';
        BudgetPromt::updateOrCreate(
            ['name' => self::CAR_ECONOMY_PROMPT_NAME],
            ['content' => $carEconomyPrompt]
        );
        $saved['car_economy_prompt'] = $carEconomyPrompt;

        $carMediumPrompt = $validated['car_medium_prompt'] ?? '';
        BudgetPromt::updateOrCreate(
            ['name' => self::CAR_MEDIUM_PROMPT_NAME],
            ['content' => $carMediumPrompt]
        );
        $saved['car_medium_prompt'] = $carMediumPrompt;

        $carLuxuryPrompt = $validated['car_luxury_prompt'] ?? '';
        BudgetPromt::updateOrCreate(
            ['name' => self::CAR_LUXURY_PROMPT_NAME],
            ['content' => $carLuxuryPrompt]
        );
        $saved['car_luxury_prompt'] = $carLuxuryPrompt;

        foreach ([
            BudgetPriorityAdjustmentService::STRICT_PROMPT => 'budget_priority_strict_percent',
            BudgetPriorityAdjustmentService::BALANCE_PROMPT => 'budget_priority_balance_percent',
            BudgetPriorityAdjustmentService::RELAX_PROMPT => 'budget_priority_relax_percent',
        ] as $settingName => $requestKey) {
            $value = $validated[$requestKey] ?? (BudgetPriorityAdjustmentService::DEFAULTS[$settingName] ?? '0');
            BudgetPromt::updateOrCreate(
                ['name' => $settingName],
                ['content' => $value]
            );
            $saved[$requestKey] = $value;
        }

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

    private function entertainmentPromptContent(string $name): string
    {
        $content = BudgetPromt::where('name', $name)->value('content');
        if (is_string($content) && $content !== '') {
            return $content;
        }

        return BudgetPromt::where('name', self::ENTERTAINMENT_PROMPT_NAME)->value('content') ?? '';
    }

    private function settingValue(string $name): string
    {
        $value = BudgetPromt::where('name', $name)->value('content');

        return is_string($value) && $value !== '' ? $value : (BudgetPriorityAdjustmentService::DEFAULTS[$name] ?? '0');
    }
}
