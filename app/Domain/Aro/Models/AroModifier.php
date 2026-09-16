<?php

declare(strict_types=1);

namespace App\Domain\Aro\Models;

use App\Domain\Aro\Engine\Modifiers\Modifier;
use App\Domain\Aro\Engine\Modifiers\ModifierOp;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $aro_version_id
 * @property string $code
 * @property string $label
 * @property string $rule_reference
 * @property string $op
 * @property string $value
 * @property array<int,string>|null $applies_to_codes
 * @property string|null $condition_key
 * @property int $sort_order
 */
final class AroModifier extends Model
{
    protected $table = 'aro_modifiers';

    protected $keyType = 'string';

    use HasUuids;

    public $incrementing = false;

    protected $fillable = [
        'aro_version_id', 'code', 'label', 'rule_reference', 'op', 'value',
        'applies_to_codes', 'condition_key', 'sort_order',
    ];

    /** @return BelongsTo<AroVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(AroVersion::class, 'aro_version_id');
    }

    public function toEngine(): Modifier
    {
        $op = ModifierOp::from($this->op);
        $value = in_array($op, [ModifierOp::Add, ModifierOp::Floor, ModifierOp::Cap], true)
            ? Money::ofMinor((int) $this->value, 'KES')
            : $this->value;

        return new Modifier($this->code, $this->rule_reference, $op, $value, $this->sort_order);
    }

    protected function casts(): array
    {
        return ['applies_to_codes' => 'array'];
    }
}
