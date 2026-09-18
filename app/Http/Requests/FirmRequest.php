<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Tenancy\CurrentFirm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('firm.update')) {
            $firm = app(CurrentFirm::class)->get();

            return $firm !== null && ($this->user()?->can('update', $firm) ?? false);
        }

        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kra_pin' => ['nullable', 'string', 'max:20'],
            'lsk_firm_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vat_registered' => ['boolean'],
            'rounding_policy' => ['required', Rule::in(['shilling_half_up', 'cent'])],
            'default_cost_basis' => ['required', Rule::in(['advocate_client', 'party_party'])],
            'bill_number_prefix' => ['required', 'string', 'max:20'],
        ];
    }
}
