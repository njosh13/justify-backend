<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FeeAgreementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FeeAgreementRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(FeeAgreementType::class)],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'required_if:type,hourly'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0', 'required_if:type,fixed'],
            'election_communicated_at' => ['nullable', 'date'],
            'signed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string,mixed> */
    public function agreementAttributes(): array
    {
        return [
            'type' => $this->input('type'),
            'hourly_rate_cents' => $this->filled('hourly_rate') ? (int) round((float) $this->input('hourly_rate') * 100) : null,
            'fixed_amount_cents' => $this->filled('fixed_amount') ? (int) round((float) $this->input('fixed_amount') * 100) : null,
            'election_communicated_at' => $this->input('election_communicated_at') ?: null,
            'signed_at' => $this->input('signed_at') ?: null,
            'notes' => $this->input('notes') ?: null,
            'is_active' => true,
        ];
    }
}
