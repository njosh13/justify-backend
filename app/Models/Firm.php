<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\Models\Bill;
use Database\Factories\FirmFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string $plan
 * @property string $isolation
 * @property string|null $kra_pin
 * @property string|null $lsk_firm_number
 * @property string|null $address
 * @property string|null $email
 * @property string|null $phone
 * @property bool $vat_registered
 * @property string $rounding_policy
 * @property string $default_cost_basis
 * @property string $bill_number_prefix
 * @property int $bill_sequence
 */
#[UseFactory(FirmFactory::class)]
final class Firm extends Model
{
    /** @use HasFactory<FirmFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 'plan', 'kra_pin', 'lsk_firm_number', 'address', 'email', 'phone',
        'vat_registered', 'rounding_policy', 'default_cost_basis', 'bill_number_prefix',
    ];

    /** @return BelongsToMany<User, $this, FirmUser> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(FirmUser::class)
            ->withPivot(['role', 'hourly_rate_cents'])
            ->withTimestamps();
    }

    /** @return HasMany<Client, $this> */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /** @return HasMany<Matter, $this> */
    public function matters(): HasMany
    {
        return $this->hasMany(Matter::class);
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function roundsToShilling(): bool
    {
        return $this->rounding_policy === 'shilling_half_up';
    }

    protected function casts(): array
    {
        return ['vat_registered' => 'boolean', 'bill_sequence' => 'integer'];
    }
}
