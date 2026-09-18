<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PaymentRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000000000'],
            'method' => ['required', Rule::in(['mpesa', 'bank', 'cheque', 'cash', 'wht_certificate'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'received_at' => ['required', 'date', 'before_or_equal:now'],
            'allocated_to' => ['nullable', Rule::in(['fees', 'disbursements', 'interest'])],
            'wht_certificate_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array{amount_cents:int, method:string, reference:string|null, received_at:string, allocated_to:string, wht_certificate_reference:string|null, notes:string|null} */
    public function paymentAttributes(): array
    {
        return [
            'amount_cents' => (int) round((float) $this->input('amount') * 100),
            'method' => (string) $this->input('method'),
            'reference' => $this->input('reference') ?: null,
            'received_at' => (string) $this->input('received_at'),
            'allocated_to' => (string) ($this->input('allocated_to') ?: 'fees'),
            'wht_certificate_reference' => $this->input('wht_certificate_reference') ?: null,
            'notes' => $this->input('notes') ?: null,
        ];
    }
}
