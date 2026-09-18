<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Aro\Engine\Posture;
use App\Domain\Aro\Models\AroVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AroPreviewRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'aro_version_id' => ['nullable', Rule::exists(AroVersion::class, 'id')],
            'item_code' => ['required', 'string', 'max:100'],
            'basis' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'scale' => ['nullable', Rule::in(['lower', 'higher'])],
            'posture' => ['nullable', Rule::enum(Posture::class)],
            'modifier_codes' => ['nullable', 'array'],
            'modifier_codes.*' => ['string', 'max:100'],
            'modifier_amounts' => ['nullable', 'array'],
            'modifier_amounts.*' => ['nullable', 'numeric', 'min:0'],
            'certificates' => ['nullable', 'array'],
            'certificates.two_advocates' => ['boolean'],
            'certificates.senior_counsel' => ['boolean'],
            'cost_basis' => ['nullable', Rule::in(['party_party', 'advocate_client'])],
            'agreed_rate' => ['nullable', 'numeric', 'min:0'],
            'instruction_fee' => ['nullable', 'numeric', 'min:0'],
            'contested' => ['boolean'],
        ];
    }
}
