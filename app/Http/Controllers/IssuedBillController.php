<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\Exceptions\BillCannotBeIssuedException;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Services\BillIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class IssuedBillController extends Controller
{
    public function store(Request $request, Bill $bill, BillIssuer $issuer): RedirectResponse
    {
        Gate::authorize('issue', $bill);

        try {
            $issuer->issue($bill, $request->user());
        } catch (BillCannotBeIssuedException $e) {
            throw ValidationException::withMessages(['issue' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill :number issued and locked.', ['number' => $bill->number])]);

        return to_route('bills.show', $bill);
    }
}
