<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CourtLevel;
use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MatterRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'client_id' => ['required', Rule::exists(Client::class, 'id')],
            'title' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'court_level' => ['required', Rule::enum(CourtLevel::class)],
            'cause_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'status' => ['sometimes', Rule::in(['open', 'closed'])],
            'opened_on' => ['nullable', 'date'],
        ];
    }

    /** @return array<string,mixed> */
    public function matterAttributes(): array
    {
        $data = $this->safe()->except(['value']);
        $data['value_cents'] = $this->filled('value') ? (int) round((float) $this->input('value') * 100) : null;

        return $data;
    }
}
