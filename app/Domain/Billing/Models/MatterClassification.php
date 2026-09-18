<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Aro\Engine\Certificates;
use App\Domain\Aro\Engine\Posture;
use App\Domain\Aro\Models\AroItem;
use App\Domain\Aro\Models\AroVersion;
use App\Models\Matter;
use App\Tenancy\BelongsToFirm;
use Database\Factories\MatterClassificationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One active row per matter: the subject-matter value (para 21), the primary
 * head, scale, posture, certificates and whether the matter is contested or
 * exempt from scale (pro bono, legal aid).
 *
 * @property string $id
 * @property string $firm_id
 * @property string $matter_id
 * @property string $aro_version_id
 * @property string|null $aro_item_id
 * @property int|null $basis_cents
 * @property string|null $basis_limb
 * @property string|null $scale
 * @property Posture|null $posture
 * @property array{two_advocates?: bool, senior_counsel?: bool, higher_scale_order?: bool}|null $certificates
 * @property bool $contested
 * @property bool $is_exempt
 * @property string|null $exemption_reason
 * @property int|null $exempted_by
 * @property Carbon|null $exempted_at
 * @property bool $is_active
 * @property-read AroItem|null $item
 * @property-read AroVersion $version
 */
#[UseFactory(MatterClassificationFactory::class)]
final class MatterClassification extends Model
{
    /** @use HasFactory<MatterClassificationFactory> */
    use BelongsToFirm, HasFactory, HasUuids;

    protected $fillable = [
        'matter_id', 'aro_version_id', 'aro_item_id', 'basis_cents', 'basis_limb', 'scale', 'posture',
        'certificates', 'contested', 'is_exempt', 'exemption_reason', 'exempted_by', 'exempted_at', 'is_active',
    ];

    /** @return BelongsTo<Matter, $this> */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /** @return BelongsTo<AroVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(AroVersion::class, 'aro_version_id');
    }

    /** @return BelongsTo<AroItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(AroItem::class, 'aro_item_id');
    }

    public function certificatesForEngine(): Certificates
    {
        $c = $this->certificates ?? [];

        return new Certificates(
            twoAdvocates: (bool) ($c['two_advocates'] ?? false),
            seniorCounsel: (bool) ($c['senior_counsel'] ?? false),
            higherScaleOrder: (bool) ($c['higher_scale_order'] ?? false),
        );
    }

    protected function casts(): array
    {
        return [
            'basis_cents' => 'integer',
            'posture' => Posture::class,
            'certificates' => 'array',
            'contested' => 'boolean',
            'is_exempt' => 'boolean',
            'is_active' => 'boolean',
            'exempted_at' => 'datetime',
        ];
    }
}
