<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\ChargeableItem;
use App\Domain\Billing\Models\FeeAgreement;
use App\Domain\Billing\Models\MatterClassification;
use App\Enums\CourtLevel;
use App\Enums\FeeAgreementType;
use App\Tenancy\BelongsToFirm;
use Database\Factories\MatterFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $client_id
 * @property string $title
 * @property string|null $reference
 * @property CourtLevel $court_level
 * @property string|null $cause_number
 * @property string|null $description
 * @property int|null $value_cents
 * @property string $status
 * @property Carbon|null $opened_on
 * @property-read Client $client
 * @property-read MatterClassification|null $classification
 * @property-read int|null $unbilled_count
 */
#[UseFactory(MatterFactory::class)]
final class Matter extends Model
{
    /** @use HasFactory<MatterFactory> */
    use BelongsToFirm, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'client_id', 'title', 'reference', 'court_level', 'cause_number', 'description',
        'value_cents', 'status', 'opened_on',
    ];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasOne<MatterClassification, $this> */
    public function classification(): HasOne
    {
        return $this->hasOne(MatterClassification::class)->where('is_active', true)->latest();
    }

    /** @return HasMany<MatterClassification, $this> */
    public function classifications(): HasMany
    {
        return $this->hasMany(MatterClassification::class);
    }

    /** @return HasMany<FeeAgreement, $this> */
    public function feeAgreements(): HasMany
    {
        return $this->hasMany(FeeAgreement::class);
    }

    /** @return HasMany<ChargeableItem, $this> */
    public function chargeableItems(): HasMany
    {
        return $this->hasMany(ChargeableItem::class)->orderBy('occurred_on')->orderBy('created_at');
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function activeAgreement(FeeAgreementType $type): ?FeeAgreement
    {
        return $this->feeAgreements->first(fn (FeeAgreement $a) => $a->is_active && $a->type === $type);
    }

    /** Para 22: the election must be signified in writing before or with the bill. */
    public function hasCommunicatedElection(): bool
    {
        $election = $this->activeAgreement(FeeAgreementType::Schedule5Election);

        return $election !== null && $election->election_communicated_at !== null;
    }

    public function isExemptFromScale(): bool
    {
        return (bool) $this->classification?->is_exempt;
    }

    protected function casts(): array
    {
        return [
            'court_level' => CourtLevel::class,
            'value_cents' => 'integer',
            'opened_on' => 'date',
        ];
    }
}
