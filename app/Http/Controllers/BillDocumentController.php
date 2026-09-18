<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\Documents\BillPdfRenderer;
use App\Domain\Billing\Models\Bill;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BillDocumentController extends Controller
{
    /** The document as HTML — what the PDF is rendered from; a draft previews live. */
    public function preview(Bill $bill, BillPdfRenderer $renderer): Response
    {
        Gate::authorize('view', $bill);

        return response($renderer->html($bill->load(['client', 'matter', 'version', 'lines', 'firm'])));
    }

    /** The PDF: the stored file for an issued bill, rendered on the fly for a draft (watermarked). */
    public function pdf(Bill $bill, BillPdfRenderer $renderer): StreamedResponse
    {
        Gate::authorize('view', $bill);

        $bill->load(['client', 'matter', 'version', 'lines', 'firm']);
        $name = str_replace('/', '-', $bill->number ?? 'DRAFT-'.$bill->id).'.pdf';

        if ($bill->pdf_path !== null && Storage::disk('local')->exists($bill->pdf_path)) {
            return Storage::disk('local')->download($bill->pdf_path, $name);
        }

        $path = $renderer->render($bill);

        return Storage::disk('local')->download($path, $name);
    }
}
