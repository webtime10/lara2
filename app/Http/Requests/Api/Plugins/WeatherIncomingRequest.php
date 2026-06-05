<?php

namespace App\Http\Requests\Api\Plugins;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WeatherIncomingRequest extends FormRequest
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
            'month_name' => ['required', 'string', 'max:255'],
            'region_name' => ['required', 'string', 'max:255'],
            'month' => ['nullable', 'integer'],
            'region' => ['nullable', 'integer'],
            'language' => ['required', 'string', 'max:10', Rule::in(Language::activeCodes())],
        ];
    }
}
