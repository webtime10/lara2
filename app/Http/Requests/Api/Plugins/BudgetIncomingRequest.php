<?php

namespace App\Http\Requests\Api\Plugins;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetIncomingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'language' => ['required', 'string', 'max:10', Rule::in(Language::activeCodes())],
            'session_token' => ['nullable', 'string', 'max:64'],
            'answers' => ['required', 'array'],
            'answers.catalog' => ['required', 'array'],
        ];
    }
}
