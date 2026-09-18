<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Aro\Engine\Posture;
use App\Domain\Aro\Models\AroItem;
use App\Enums\ChargeableItemKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChargeableItemRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $priced = in_array($this->input('kind'), [ChargeableItemKind::Fee->value, ChargeableItemKind::Time->value], true);

        return [
            'kind' => ['required', Rule::enum(ChargeableItemKind::class)],
            'aro_item_id' => [$priced ? 'required' : 'nullable', Rule::exists(AroItem::class, 'id')],
            'description' => ['required', 'string', 'max:1000'],
            'occurred_on' => ['required', 'date'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'unit' => ['nullable', 'string', 'max:50'],
            'basis_override' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'scale_override' => ['nullable', Rule::in(['lower', 'higher'])],
            'posture_override' => ['nullable', Rule::enum(Posture::class)],
            'modifier_codes' => ['nullable', 'array'],
            'modifier_codes.*' => ['string', 'max:100'],
            'modifier_amounts' => ['nullable', 'array'],
            'modifier_amounts.*' => ['numeric', 'min:0'],
            'entered' => ['required', 'numeric', 'min:0', 'max:1000000000000'],
            'uplift_justification' => ['nullable', 'string', 'max:2000'],
            'is_billable' => ['boolean'],
        ];
    }

    /** @return array<string,mixed> */
    public function itemAttributes(): array
    {
        $cents = fn (?string $key) => $this->filled($key) ? (int) round((float) $this->input($key) * 100) : null;

        return [
            'kind' => $this->input('kind'),
            'aro_item_id' => $this->input('aro_item_id') ?: null,
            'description' => $this->input('description'),
            'occurred_on' => $this->input('occurred_on'),
            'quantity' => $this->filled('quantity') ? (float) $this->input('quantity') : null,
            'unit' => $this->input('unit') ?: null,
            'basis_override_cents' => $cents('basis_override'),
            'scale_override' => $this->input('scale_override') ?: null,
            'posture_override' => $this->input('posture_override') ?: null,
            'modifier_codes' => array_values(array_filter((array) $this->input('modifier_codes', []))),
            'modifier_amounts' => collect((array) $this->input('modifier_amounts', []))
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->map(fn ($v) => (int) round((float) $v * 100))
                ->all(),
            'entered_cents' => (int) round((float) $this->input('entered') * 100),
            'uplift_justification' => $this->input('uplift_justification') ?: null,
            'is_billable' => $this->boolean('is_billable', true),
        ];
    }
}
