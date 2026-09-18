<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Aro\Engine\Posture;
use App\Domain\Aro\Models\AroItem;
use App\Enums\ChargeableItemKind;
use App\Models\Matter;
use App\Models\User;
use App\Tenancy\BelongsToFirm;
use Database\Factories\ChargeableItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A unit of chargeable work on a matter (plan §3.4). Fee and time items carry
 * an ARO head and the engine's computed minimum; disbursements and recharges
 * carry only the amount entered.
 *
 * @property string $id
 * @property string $firm_id
 * @property string $matter_id
 * @property int|null $advocate_id
 * @property ChargeableItemKind $kind
 * @property string|null $aro_item_id
 * @property string $description
 * @property Carbon $occurred_on
 * @property string|null $quantity
 * @property string|null $unit
 * @property int|null $basis_override_cents
 * @property string|null $scale_override
 * @property Posture|null $posture_override
 * @property string[]|null $modifier_codes
 * @property array<string,int>|null $modifier_amounts
 * @property int $entered_cents
 * @property int|null $computed_minimum_cents
 * @property string|null $computed_bound
 * @property int|null $computed_ceiling_cents
 * @property array<string,mixed>|null $computed_snapshot
 * @property string|null $uplift_justification
 * @property bool $is_billable
 * @property string|null $bill_id
 * @property-read Matter $matter
 * @property-read AroItem|null $aroItem
 */
#[UseFactory(ChargeableItemFactory::class)]
final class ChargeableItem extends Model
{
    /** @use HasFactory<ChargeableItemFactory> */
    use BelongsToFirm, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'matter_id', 'advocate_id', 'kind', 'aro_item_id', 'description', 'occurred_on', 'quantity', 'unit',
        'basis_override_cents', 'scale_override', 'posture_override', 'modifier_codes', 'modifier_amounts',
        'entered_cents', 'computed_minimum_cents', 'computed_bound', 'computed_ceiling_cents',
        'computed_snapshot', 'uplift_justification', 'is_billable',
    ];

    /** @return BelongsTo<Matter, $this> */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /** @return BelongsTo<AroItem, $this> */
    public function aroItem(): BelongsTo
    {
        return $this->belongsTo(AroItem::class, 'aro_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function advocate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advocate_id');
    }

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeUnbilled(Builder $query): void
    {
        $query->whereNull('bill_id')->where('is_billable', true);
    }

    public function isPricedByEngine(): bool
    {
        return $this->kind->isProfessionalFee() && $this->aro_item_id !== null;
    }

    /** Para 3 shortfall for this line, in cents. */
    public function shortfallCents(): int
    {
        if ($this->computed_minimum_cents === null || $this->computed_bound === 'maximum') {
            return 0;
        }

        return max(0, $this->computed_minimum_cents - $this->entered_cents);
    }

    public function isBilled(): bool
    {
        return $this->bill_id !== null;
    }

    protected function casts(): array
    {
        return [
            'kind' => ChargeableItemKind::class,
            'occurred_on' => 'date',
            'quantity' => 'decimal:4',
            'basis_override_cents' => 'integer',
            'posture_override' => Posture::class,
            'modifier_codes' => 'array',
            'modifier_amounts' => 'array',
            'entered_cents' => 'integer',
            'computed_minimum_cents' => 'integer',
            'computed_ceiling_cents' => 'integer',
            'computed_snapshot' => 'array',
            'is_billable' => 'boolean',
        ];
    }
}
