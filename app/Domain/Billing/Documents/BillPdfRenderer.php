<?php

declare(strict_types=1);

namespace App\Domain\Billing\Documents;

use App\Domain\Billing\Models\Bill;
use App\Enums\BillType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the para 69 bill of costs or the fee note / tax invoice from the
 * bill's frozen snapshot, so a re-render years later is byte-for-byte the
 * same document.
 */
final class BillPdfRenderer
{
    public function html(Bill $bill): string
    {
        return view($this->view($bill), ['bill' => $bill, 'snapshot' => $bill->computed_snapshot ?? []])->render();
    }

    /** Renders to the local disk and returns the stored path. */
    public function render(Bill $bill): string
    {
        $path = sprintf('bills/%s/%s.pdf', $bill->firm_id, $bill->id);

        $pdf = Pdf::loadHTML($this->html($bill))->setPaper('a4');
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /** @return view-string */
    public function view(Bill $bill): string
    {
        return $bill->type === BillType::BillOfCosts ? 'bills.bill-of-costs' : 'bills.fee-note';
    }
}
