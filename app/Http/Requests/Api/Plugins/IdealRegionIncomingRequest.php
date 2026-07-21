<?php

namespace App\Http\Requests\Api\Plugins;

use Illuminate\Foundation\Http\FormRequest;

class IdealRegionIncomingRequest extends FormRequest
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
            'language' => ['required', 'string', 'max:10'],
            'session_token' => ['nullable', 'string', 'max:64'],
            'manufacturer_id' => ['nullable', 'integer', 'min:1'],
            'answers' => ['required', 'array'],
            'answers.catalog' => ['required', 'array'],
        ];
    }
}
