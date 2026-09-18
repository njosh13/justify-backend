<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ClientRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'client_number' => ['nullable', 'string', 'max:50'],
            'client_type' => ['required', Rule::in(['individual', 'company', 'government'])],
            'kra_pin' => ['nullable', 'string', 'max:20'],
            'id_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_withholding_agent' => ['boolean'],
            'is_vat_exempt' => ['boolean'],
            'vat_exemption_reference' => ['nullable', 'string', 'max:100', 'required_if:is_vat_exempt,true'],
        ];
    }
}
