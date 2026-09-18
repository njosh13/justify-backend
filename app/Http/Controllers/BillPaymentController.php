<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Services\BillLifecycle;
use App\Http\Requests\PaymentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

final class BillPaymentController extends Controller
{
    public function store(PaymentRequest $request, Bill $bill, BillLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('recordPayment', $bill);

        try {
            $lifecycle->recordPayment($bill, $request->user(), $request->paymentAttributes());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return to_route('bills.show', $bill);
    }
}
