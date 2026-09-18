<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Enums\FeeAgreementType;
use App\Models\Client;
use App\Models\Matter;
use App\Tenancy\BelongsToFirm;
use Database\Factories\FeeAgreementFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $matter_id
 * @property string $client_id
 * @property FeeAgreementType $type
 * @property int|null $hourly_rate_cents
 * @property int|null $fixed_amount_cents
 * @property Carbon|null $election_communicated_at
 * @property Carbon|null $signed_at
 * @property string|null $notes
 * @property bool $is_active
 */
#[UseFactory(FeeAgreementFactory::class)]
final class FeeAgreement extends Model
{
    /** @use HasFactory<FeeAgreementFactory> */
    use BelongsToFirm, HasFactory, HasUuids;

    protected $fillable = [
        'matter_id', 'client_id', 'type', 'hourly_rate_cents', 'fixed_amount_cents',
        'election_communicated_at', 'signed_at', 'notes', 'is_active',
    ];

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

    protected function casts(): array
    {
        return [
            'type' => FeeAgreementType::class,
            'hourly_rate_cents' => 'integer',
            'fixed_amount_cents' => 'integer',
            'election_communicated_at' => 'datetime',
            'signed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
