<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Aro\Engine\CostBasis;
use App\Domain\Aro\Models\AroVersion;
use App\Domain\Billing\Exceptions\BillLockedException;
use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Models\Client;
use App\Models\Matter;
use App\Models\User;
use App\Tenancy\BelongsToFirm;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $matter_id
 * @property string $client_id
 * @property string|null $number
 * @property BillType $type
 * @property CostBasis $cost_basis
 * @property string $aro_version_id
 * @property BillStatus $status
 * @property int $fees_cents
 * @property int $recharges_cents
 * @property int $disbursements_cents
 * @property int $vat_cents
 * @property int $wht_expected_cents
 * @property int $total_cents
 * @property int $paid_cents
 * @property Carbon|null $issued_at
 * @property int|null $issued_by
 * @property Carbon|null $delivered_at
 * @property string|null $delivery_method
 * @property Carbon|null $deemed_agreed_at
 * @property Carbon|null $disputed_at
 * @property Carbon|null $interest_claimed_at
 * @property Carbon|null $paid_in_full_at
 * @property array<string,mixed>|null $computed_snapshot
 * @property string|null $pdf_path
 * @property string|null $original_bill_id
 * @property Carbon|null $locked_at
 * @property-read Matter $matter
 * @property-read Client $client
 * @property-read AroVersion $version
 * @property-read Collection<int, BillLine> $lines
 * @property-read Collection<int, BillEvent> $events
 * @property-read Collection<int, Payment> $payments
 */
#[UseFactory(BillFactory::class)]
final class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use BelongsToFirm, HasFactory, HasUuids, SoftDeletes;

    /** Columns that may still change once a bill is locked (lifecycle only, never money). */
    public const MUTABLE_AFTER_LOCK = [
        'status', 'paid_cents', 'delivered_at', 'delivery_method', 'deemed_agreed_at', 'disputed_at',
        'interest_claimed_at', 'paid_in_full_at', 'pdf_path', 'updated_at', 'deleted_at',
    ];

    protected $fillable = [
        'matter_id', 'client_id', 'number', 'type', 'cost_basis', 'aro_version_id', 'status',
        'fees_cents', 'recharges_cents', 'disbursements_cents', 'vat_cents', 'wht_expected_cents', 'total_cents',
        'paid_cents', 'issued_at', 'issued_by', 'delivered_at', 'delivery_method', 'deemed_agreed_at', 'disputed_at',
        'interest_claimed_at', 'paid_in_full_at', 'computed_snapshot', 'pdf_path', 'original_bill_id', 'locked_at',
    ];

    protected static function booted(): void
    {
        self::updating(function (Bill $bill): void {
            if ($bill->getOriginal('locked_at') === null) {
                return;
            }

            $changed = array_diff(array_keys($bill->getDirty()), self::MUTABLE_AFTER_LOCK);
            if ($changed !== []) {
                throw new BillLockedException(sprintf(
                    'Bill %s is locked; %s cannot change. Issue a credit note instead.',
                    $bill->number ?? $bill->id,
                    implode(', ', $changed),
                ));
            }
        });

        self::deleting(function (Bill $bill): void {
            if ($bill->locked_at !== null) {
                throw new BillLockedException("Bill {$bill->number} is locked and cannot be deleted; void it with a credit note.");
            }
        });
    }

    /** @return BelongsTo<Matter, $this> */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<AroVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(AroVersion::class, 'aro_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return HasMany<BillLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class)->orderBy('seq');
    }

    /** @return HasMany<ChargeableItem, $this> */
    public function chargeableItems(): HasMany
    {
        return $this->hasMany(ChargeableItem::class);
    }

    /** @return HasMany<BillEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(BillEvent::class)->orderBy('created_at');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('received_at');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === BillStatus::Draft;
    }

    public function outstandingCents(): int
    {
        return max(0, $this->total_cents - $this->paid_cents);
    }

    /** @param array<string,mixed> $payload */
    public function recordEvent(string $type, ?User $user = null, array $payload = []): BillEvent
    {
        return $this->events()->create([
            'firm_id' => $this->firm_id,
            'type' => $type,
            'user_id' => $user?->id,
            'payload' => $payload === [] ? null : $payload,
            'created_at' => now(),
        ]);
    }

    protected function casts(): array
    {
        return [
            'type' => BillType::class,
            'cost_basis' => CostBasis::class,
            'status' => BillStatus::class,
            'fees_cents' => 'integer',
            'recharges_cents' => 'integer',
            'disbursements_cents' => 'integer',
            'vat_cents' => 'integer',
            'wht_expected_cents' => 'integer',
            'total_cents' => 'integer',
            'paid_cents' => 'integer',
            'issued_at' => 'datetime',
            'delivered_at' => 'datetime',
            'deemed_agreed_at' => 'datetime',
            'disputed_at' => 'datetime',
            'interest_claimed_at' => 'datetime',
            'paid_in_full_at' => 'datetime',
            'computed_snapshot' => 'array',
            'locked_at' => 'datetime',
        ];
    }
}
