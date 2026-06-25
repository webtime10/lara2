<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuizAnswer;
use App\Services\CarBudgetGeminiService;
use App\Services\Budget\TripBudgetTotalCalculator;
use App\Services\EntertainmentGeminiService;
use App\Services\FoodBudgetGeminiService;
use Illuminate\Http\Request;

class BudgetCalculatorController extends Controller
{
    public function __construct(
        private readonly TripBudgetTotalCalculator $tripBudgetTotalCalculator,
        private readonly EntertainmentGeminiService $entertainmentGeminiService,
        private readonly FoodBudgetGeminiService $foodBudgetGeminiService,
        private readonly CarBudgetGeminiService $carBudgetGeminiService,
    ) {}

    public function index(Request $request)
    {
        $pageTitle = 'Budget калькулятор';

        $answers = QuizAnswer::query()
            ->when($request->filled('region'), function ($query) use ($request) {
                $query->where('region', 'like', '%' . trim((string) $request->region) . '%');
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $answers->getCollection()->each(function (QuizAnswer $answer): void {
            $totalPeople = (int) $answer->travelers_count + (int) $answer->children_count;
            if ((int) $answer->total_people !== $totalPeople) {
                $answer->forceFill(['total_people' => $totalPeople])->save();
                $answer->total_people = $totalPeople;
            }

            if ($answer->entertainment_budget_total === null || $answer->entertainment_budget_total === '') {
                $amount = $this->entertainmentGeminiService->fallbackAmountForAnswer($answer);
                $answer->forceFill([
                    'entertainment_budget_total' => '$' . number_format($amount, 0, '.', ' '),
                ])->save();
                $answer->entertainment_budget_total = '$' . number_format($amount, 0, '.', ' ');
            }

            if ($answer->food_budget_total === null || $answer->food_budget_total === '') {
                $amount = $this->foodBudgetGeminiService->fallbackAmountForAnswer($answer);
                $answer->forceFill([
                    'food_budget_total' => '$' . number_format($amount, 0, '.', ' '),
                ])->save();
                $answer->food_budget_total = '$' . number_format($amount, 0, '.', ' ');
            }

            if ($answer->car_budget_total === null || $answer->car_budget_total === '') {
                $amount = $this->carBudgetGeminiService->fallbackAmountForAnswer($answer);
                $answer->forceFill([
                    'car_budget_total' => '$' . number_format($amount, 0, '.', ' '),
                ])->save();
                $answer->car_budget_total = '$' . number_format($amount, 0, '.', ' ');
            }

            $this->tripBudgetTotalCalculator->applyTo($answer);
        });

        return view('admin.budget-calculator.index', compact('answers', 'pageTitle'));
    }

    public function bulkDelete(Request $request)
    {
        $ids = collect((array) $request->input('selected', []))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isNotEmpty()) {
            QuizAnswer::query()->whereIn('id', $ids)->delete();

            return redirect()
                ->route('admin.budget-calculator.index', $request->query())
                ->with('success', 'Удалено записей: ' . $ids->count());
        }

        return redirect()
            ->route('admin.budget-calculator.index', $request->query())
            ->with('error', 'Выберите записи для удаления.');
    }
}
