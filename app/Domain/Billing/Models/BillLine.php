<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Aro\Models\AroItem;
use App\Domain\Billing\Exceptions\BillLockedException;
use App\Tenancy\BelongsToFirm;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Para 69 five-column line: date, serial number, particulars, charge claimed,
 * taxing officer's deduction.
 *
 * @property string $id
 * @property string $bill_id
 * @property string $firm_id
 * @property int $seq
 * @property Carbon|null $dated_on
 * @property string $particulars
 * @property int $claimed_cents
 * @property int|null $taxed_off_cents
 * @property string|null $aro_item_id
 * @property string|null $rule_reference
 * @property string|null $provenance
 * @property string|null $chargeable_item_id
 * @property string $section
 * @property string $vat_rate
 * @property int $vat_cents
 * @property string|null $tax_type_code
 */
final class BillLine extends Model
{
    use BelongsToFirm, HasUuids;

    public const SECTION_FEES = 'fees';

    public const SECTION_DISBURSEMENTS = 'disbursements';

    public const SECTION_TAXATION_ATTENDANCE = 'taxation_attendance';

    protected $fillable = [
        'bill_id', 'seq', 'dated_on', 'particulars', 'claimed_cents', 'taxed_off_cents', 'aro_item_id',
        'rule_reference', 'provenance', 'chargeable_item_id', 'section', 'vat_rate', 'vat_cents', 'tax_type_code',
    ];

    protected static function booted(): void
    {
        $guard = function (BillLine $line, string $op): void {
            $bill = $line->bill()->withoutGlobalScopes()->first();
            if ($bill?->isLocked()) {
                $changed = array_diff(array_keys($line->getDirty()), ['taxed_off_cents', 'updated_at']);
                if ($op !== 'update' || $changed !== []) {
                    throw new BillLockedException("Bill {$bill->number} is locked; its lines are append-only");
                }
            }
        };

        self::updating(fn (BillLine $line) => $guard($line, 'update'));
        self::deleting(fn (BillLine $line) => $guard($line, 'delete'));
    }

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return BelongsTo<AroItem, $this> */
    public function aroItem(): BelongsTo
    {
        return $this->belongsTo(AroItem::class, 'aro_item_id');
    }

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'dated_on' => 'date',
            'claimed_cents' => 'integer',
            'taxed_off_cents' => 'integer',
            'vat_rate' => 'decimal:6',
            'vat_cents' => 'integer',
        ];
    }
}
