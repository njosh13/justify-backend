<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DeliverBillRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'delivery_method' => ['required', Rule::in(['email', 'hand', 'post', 'courier', 'portal'])],
            'delivered_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
