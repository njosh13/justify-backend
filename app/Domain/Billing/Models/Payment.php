<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Models\User;
use App\Tenancy\BelongsToFirm;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $bill_id
 * @property int $amount_cents
 * @property string $method
 * @property string|null $reference
 * @property Carbon $received_at
 * @property string $allocated_to
 * @property string|null $wht_certificate_reference
 * @property string|null $notes
 * @property int|null $recorded_by
 */
final class Payment extends Model
{
    use BelongsToFirm, HasUuids;

    protected $fillable = [
        'bill_id', 'amount_cents', 'method', 'reference', 'received_at', 'allocated_to',
        'wht_certificate_reference', 'notes', 'recorded_by',
    ];

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'received_at' => 'date'];
    }
}
