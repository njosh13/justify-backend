<?php

declare(strict_types=1);

namespace App\Domain\Aro\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $aro_version_id
 * @property string $code
 * @property int $schedule
 * @property string $label
 * @property string $rule_reference
 * @property string|null $basis_type
 * @property string $computation
 * @property string $scale_variant
 * @property string $applies_cost_basis
 * @property bool $is_instruction_fee
 * @property string|null $unit_label
 * @property int|null $units_included
 * @property int|null $included_amount_cents
 * @property array<string,mixed>|null $params
 * @property bool $is_active
 * @property-read Collection<int, AroBand> $bands
 */
final class AroItem extends Model
{
    protected $table = 'aro_items';

    protected $keyType = 'string';

    use HasUuids;

    public $incrementing = false;

    protected $fillable = [
        'aro_version_id', 'code', 'schedule', 'label', 'rule_reference', 'basis_type',
        'computation', 'scale_variant', 'applies_cost_basis', 'is_instruction_fee',
        'unit_label', 'units_included', 'included_amount_cents', 'params', 'is_active',
    ];

    /** @return BelongsTo<AroVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(AroVersion::class, 'aro_version_id');
    }

    /** @return HasMany<AroBand, $this> */
    public function bands(): HasMany
    {
        return $this->hasMany(AroBand::class, 'aro_item_id')->orderBy('sort');
    }

    /**
     * Modifiers in the same version that may be applied to this head.
     *
     * @return Collection<int, AroModifier>
     */
    public function applicableModifiers(): Collection
    {
        return AroModifier::query()
            ->where('aro_version_id', $this->aro_version_id)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->filter(fn (AroModifier $m) => $m->applies_to_codes === null || in_array($this->code, $m->applies_to_codes, true))
            ->values();
    }

    public function postureTable(?string $scale): ?string
    {
        $spec = $this->params['posture_table'] ?? null;
        if (is_array($spec)) {
            return $scale === null ? null : ($spec[$scale] ?? null);
        }

        return is_string($spec) ? $spec : null;
    }

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'is_instruction_fee' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
