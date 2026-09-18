<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Services\BillLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

final class BillInterestClaimController extends Controller
{
    public function store(Request $request, Bill $bill, BillLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('claimInterest', $bill);

        try {
            $lifecycle->claimInterest($bill, $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['interest' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Interest claimed under para 7 at 14% p.a. from one month after delivery.')]);

        return to_route('bills.show', $bill);
    }
}
