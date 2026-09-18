<?php

declare(strict_types=1);

namespace App\Domain\Aro\Models;

use Database\Factories\AroVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int|null $published_by
 * @property Carbon|null $published_at
 */
#[UseFactory(AroVersionFactory::class)]
final class AroVersion extends Model
{
    /** @use HasFactory<AroVersionFactory> */
    use HasFactory;

    protected $table = 'aro_versions';

    protected $keyType = 'string';

    use HasUuids;

    public $incrementing = false;

    protected $fillable = [
        'code', 'legal_notice', 'effective_from', 'effective_to',
        'source_url', 'source_sha256', 'status', 'reviewed_by', 'reviewed_at', 'published_by', 'published_at',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_PUBLISHED = 'published';

    /**
     * The version bills are drawn on: the latest published one. Falls back to
     * the latest version of any status only when nothing is published, so the
     * calculator sandbox works on a fresh install — BillIssuer still refuses
     * an unpublished version.
     */
    public static function current(): ?self
    {
        return self::query()->where('status', self::STATUS_PUBLISHED)->orderByDesc('effective_from')->first()
            ?? self::query()->orderByDesc('effective_from')->first();
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

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
        return ['effective_from' => 'date', 'effective_to' => 'date', 'reviewed_at' => 'datetime', 'published_at' => 'datetime'];
    }
}
