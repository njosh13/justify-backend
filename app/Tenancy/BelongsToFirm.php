<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Firm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Row-level tenancy (plan §2.2): a global scope on `firm_id` and automatic
 * assignment on create. Mass-assigned `firm_id` is ignored: the column is
 * never fillable, so a request cannot move a record to another firm.
 *
 * @mixin Model
 */
trait BelongsToFirm
{
    public static function bootBelongsToFirm(): void
    {
        static::addGlobalScope(new FirmScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('firm_id') === null) {
                $firmId = app(CurrentFirm::class)->id()
                    ?? throw new RuntimeException(static::class.' created without a current firm');

                $model->setAttribute('firm_id', $firmId);
            }
        });
    }

    /** @return BelongsTo<Firm, $this> */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }
}
