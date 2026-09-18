<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Models\Firm;
use Illuminate\Support\Facades\DB;

/** Per-firm sequence, `PREFIX/YYYY/0001`, reserved under a row lock. */
final class BillNumberer
{
    public function next(Firm $firm): string
    {
        return DB::transaction(function () use ($firm) {
            $locked = Firm::query()->whereKey($firm->id)->lockForUpdate()->firstOrFail();
            $locked->bill_sequence += 1;
            $locked->save();

            $firm->bill_sequence = $locked->bill_sequence;

            return sprintf('%s/%s/%04d', $locked->bill_number_prefix, now()->format('Y'), $locked->bill_sequence);
        });
    }
}
