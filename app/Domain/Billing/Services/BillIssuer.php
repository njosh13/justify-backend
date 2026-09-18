<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Documents\BillPdfRenderer;
use App\Domain\Billing\Exceptions\BillCannotBeIssuedException;
use App\Domain\Billing\Models\Bill;
use App\Enums\BillStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Plan §7 "Issue": refuse on shortfall without exemption/election, refuse an
 * unpublished ARO version, number, lock, snapshot, render the PDF.
 */
final class BillIssuer
{
    public function __construct(
        private readonly ShortfallCalculator $shortfall,
        private readonly BillNumberer $numberer,
        private readonly BillPdfRenderer $pdf,
    ) {}

    public function issue(Bill $bill, User $user): Bill
    {
        $bill->loadMissing(['matter.classification', 'matter.feeAgreements', 'firm', 'version', 'chargeableItems.aroItem']);

        if (! $bill->isDraft()) {
            throw new BillCannotBeIssuedException("Bill {$bill->number} has already been issued", 'already_issued');
        }

        if (! $bill->version->isPublished()) {
            throw new BillCannotBeIssuedException(
                "ARO version {$bill->version->code} is not published; a bill may only be drawn on published law",
                'aro_version_not_published',
            );
        }

        $report = $this->shortfall->forMatter($bill->matter, $bill->chargeableItems);
        if ($report['blocking']) {
            throw new BillCannotBeIssuedException(
                'Fees fall below the statutory minimum (para 3); raise the lines, record an exemption, or communicate a para 22 election in writing',
                'below_statutory_minimum',
                $report,
            );
        }

        return DB::transaction(function () use ($bill, $user) {
            $bill->forceFill([
                'number' => $this->numberer->next($bill->firm),
                'status' => BillStatus::Issued,
                'issued_at' => now(),
                'issued_by' => $user->id,
                'locked_at' => now(),
            ])->save();

            $bill->recordEvent('issued', $user, ['number' => $bill->number]);

            $bill->pdf_path = $this->pdf->render($bill->refresh());
            $bill->save();

            return $bill;
        });
    }
}
