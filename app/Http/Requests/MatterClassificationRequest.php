<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Aro\Engine\Posture;
use App\Domain\Aro\Models\AroItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MatterClassificationRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'aro_item_id' => ['nullable', Rule::exists(AroItem::class, 'id')],
            'basis' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'basis_limb' => ['nullable', Rule::in(['deed_price', 'stamp_duty_value', 'estate_duty_value', 'last_sale_10y', 'market_value_3y', 'sum_sued', 'sum_found_due', 'annual_rent', 'gross_estate', 'net_estate'])],
            'scale' => ['nullable', Rule::in(['lower', 'higher'])],
            'posture' => ['nullable', Rule::enum(Posture::class)],
            'certificates' => ['nullable', 'array'],
            'certificates.two_advocates' => ['boolean'],
            'certificates.senior_counsel' => ['boolean'],
            'certificates.higher_scale_order' => ['boolean'],
            'contested' => ['boolean'],
            'is_exempt' => ['boolean'],
            'exemption_reason' => ['nullable', 'string', 'max:255', 'required_if:is_exempt,true'],
        ];
    }

    /** @return array<string,mixed> */
    public function classificationAttributes(): array
    {
        $data = $this->safe()->except(['basis']);
        $data['basis_cents'] = $this->filled('basis') ? (int) round((float) $this->input('basis') * 100) : null;
        $data['certificates'] = $this->input('certificates') ?: null;

        return $data;
    }
}
