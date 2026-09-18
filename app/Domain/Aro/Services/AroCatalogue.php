<?php

declare(strict_types=1);

namespace App\Domain\Aro\Services;

use App\Domain\Aro\Models\AroItem;
use App\Domain\Aro\Models\AroModifier;
use App\Domain\Aro\Models\AroVersion;
use Illuminate\Support\Collection;

/**
 * Read model of the ARO catalogue for pickers and the calculator: each head
 * with the inputs it needs and the modifiers that may attach to it.
 */
final class AroCatalogue
{
    /**
     * @return Collection<int, array<string,mixed>>
     */
    public function items(AroVersion $version, ?int $schedule = null, ?string $search = null, bool $activeOnly = true): Collection
    {
        $modifiers = AroModifier::query()
            ->where('aro_version_id', $version->id)
            ->orderBy('sort_order')->orderBy('code')
            ->get();

        return AroItem::query()
            ->where('aro_version_id', $version->id)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->when($schedule !== null, fn ($q) => $q->where('schedule', $schedule))
            ->when($search !== null && $search !== '', function ($q) use ($search) {
                $like = '%'.mb_strtolower($search).'%';
                $q->where(fn ($w) => $w
                    ->whereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(label) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(rule_reference) LIKE ?', [$like]));
            })
            ->orderBy('schedule')->orderBy('code')
            ->get()
            ->map(fn (AroItem $item) => $this->describe($item, $modifiers));
    }

    /**
     * @param  Collection<int, AroModifier>  $modifiers
     * @return array<string,mixed>
     */
    public function describe(AroItem $item, Collection $modifiers): array
    {
        $params = $item->params ?? [];
        $postureTable = $params['posture_table'] ?? null;

        return [
            'id' => $item->id,
            'code' => $item->code,
            'schedule' => $item->schedule,
            'label' => $item->label,
            'rule_reference' => $item->rule_reference,
            'basis_type' => $item->basis_type,
            'computation' => $item->computation,
            'scale_variant' => $item->scale_variant,
            'unit_label' => $item->unit_label,
            'units_included' => $item->units_included,
            'included_amount_cents' => $item->included_amount_cents,
            'is_instruction_fee' => $item->is_instruction_fee,
            'applies_cost_basis' => $item->applies_cost_basis,
            'is_active' => $item->is_active,
            'bound' => match ($item->computation) {
                'discretionary_floor' => 'minimum',
                'discretionary_cap' => 'maximum',
                default => match ($params['bound'] ?? null) {
                    'min' => 'minimum', 'max' => 'maximum', default => 'prescribed'
                },
            },
            'ceiling_cents' => $params['ceiling_cents'] ?? null,
            'note' => $params['note'] ?? $params['provisional'] ?? $params['pending'] ?? null,
            'needs' => [
                'basis' => in_array($item->computation, ['tiered', 'bracket_rate', 'base_plus_rate', 'per_unit_bands'], true),
                'quantity' => in_array($item->computation, ['per_unit', 'per_folio', 'per_unit_bands', 'agreed_rate', 'flat', 'discretionary_floor', 'discretionary_cap'], true),
                'quantity_required' => in_array($item->computation, ['per_unit', 'per_folio', 'agreed_rate'], true),
                'scale' => $item->scale_variant === 'lower_higher',
                'posture' => $postureTable !== null,
                'posture_table' => $postureTable,
                'certificates' => $item->schedule === 6 && $item->is_instruction_fee,
                'agreed_rate' => $item->computation === 'agreed_rate',
                'instruction_fee' => $item->computation === 'getting_up',
                'contested' => $item->applies_cost_basis === 'contested_only',
            ],
            'pointer_target' => $item->computation === 'pointer' ? ($params['target'] ?? null) : null,
            'modifiers' => $modifiers
                ->filter(fn (AroModifier $m) => $m->applies_to_codes === null || in_array($item->code, $m->applies_to_codes, true))
                ->map(fn (AroModifier $m) => [
                    'code' => $m->code,
                    'label' => $m->label,
                    'rule_reference' => $m->rule_reference,
                    'op' => $m->op,
                    'value' => $m->value,
                    'condition_key' => $m->condition_key,
                    'needs_amount' => $m->op === 'add',
                ])
                ->values()
                ->all(),
        ];
    }
}
