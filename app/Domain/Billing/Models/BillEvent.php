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
 * Append-only audit trail on a bill.
 *
 * @property string $id
 * @property string $bill_id
 * @property string $firm_id
 * @property string $type
 * @property int|null $user_id
 * @property array<string,mixed>|null $payload
 * @property Carbon $created_at
 * @property-read User|null $user
 */
final class BillEvent extends Model
{
    use BelongsToFirm, HasUuids;

    public $timestamps = false;

    protected $fillable = ['bill_id', 'firm_id', 'type', 'user_id', 'payload', 'created_at'];

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime'];
    }
}
