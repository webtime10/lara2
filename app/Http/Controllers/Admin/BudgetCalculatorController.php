<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuizAnswer;
use Illuminate\Http\Request;

class BudgetCalculatorController extends Controller
{
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

        return view('admin.budget-calculator.index', compact('answers', 'pageTitle'));
    }
}
