<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Services\BillLifecycle;
use App\Http\Requests\DeliverBillRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

final class DeliveredBillController extends Controller
{
    public function store(DeliverBillRequest $request, Bill $bill, BillLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('deliver', $bill);

        try {
            $lifecycle->deliver(
                $bill,
                $request->user(),
                $request->input('delivery_method'),
                $request->filled('delivered_at') ? Carbon::parse($request->input('delivered_at')) : null,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['delivery_method' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Delivery recorded. Deemed agreed one month from delivery unless disputed (para 6).')]);

        return to_route('bills.show', $bill);
    }
}
