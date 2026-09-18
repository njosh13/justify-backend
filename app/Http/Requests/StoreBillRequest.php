<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BillType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBillRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(BillType::class)],
            'cost_basis' => ['required', Rule::in(['party_party', 'advocate_client'])],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['string', 'uuid'],
        ];
    }
}
