<?php

declare(strict_types=1);

namespace App\Domain\Aro\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $aro_item_id
 * @property string|null $scale
 * @property int $lower_cents
 * @property int|null $upper_cents
 * @property int|null $fixed_cents
 * @property numeric-string|null $rate
 * @property int|null $floor_cents
 * @property int $sort
 */
final class AroBand extends Model
{
    protected $table = 'aro_bands';

    protected $keyType = 'string';

    use HasUuids;

    public $incrementing = false;

    protected $fillable = ['aro_item_id', 'scale', 'lower_cents', 'upper_cents', 'fixed_cents', 'rate', 'floor_cents', 'sort'];

    /** @return BelongsTo<AroItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(AroItem::class, 'aro_item_id');
    }

    protected function casts(): array
    {
        return ['rate' => 'decimal:8'];
    }
}
