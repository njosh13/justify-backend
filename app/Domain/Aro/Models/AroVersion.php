<?php

declare(strict_types=1);

namespace App\Domain\Aro\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $legal_notice
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property string|null $source_url
 * @property string|null $source_sha256
 * @property string $status
 */
final class AroVersion extends Model
{
    protected $table = 'aro_versions';

    protected $keyType = 'string';

    use HasUuids;

    public $incrementing = false;

    protected $fillable = [
        'code', 'legal_notice', 'effective_from', 'effective_to',
        'source_url', 'source_sha256', 'status', 'reviewed_by', 'reviewed_at',
    ];

    /** @return HasMany<AroItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AroItem::class, 'aro_version_id');
    }

    /** @return HasMany<AroModifier, $this> */
    public function modifiers(): HasMany
    {
        return $this->hasMany(AroModifier::class, 'aro_version_id');
    }

    /** @return HasMany<AroInterpretation, $this> */
    public function interpretations(): HasMany
    {
        return $this->hasMany(AroInterpretation::class, 'aro_version_id');
    }

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'reviewed_at' => 'datetime'];
    }
}
