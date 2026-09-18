<?php

declare(strict_types=1);

namespace App\Domain\Aro\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $aro_version_id
 * @property string|null $firm_id set when a firm adopts its own reading (plan §6)
 * @property string $code
 * @property string $rule_reference
 * @property string $question
 * @property string $decision
 * @property string|null $rationale
 * @property string $status
 */
final class AroInterpretation extends Model
{
    protected $table = 'aro_interpretations';

    protected $keyType = 'string';

    use HasUuids;

    public $incrementing = false;

    protected $fillable = [
        'aro_version_id', 'firm_id', 'code', 'rule_reference', 'question', 'decision',
        'rationale', 'decided_by', 'decided_at', 'status',
    ];

    /** @return BelongsTo<AroVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(AroVersion::class, 'aro_version_id');
    }

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }
}
