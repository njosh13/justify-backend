<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\Models\FeeAgreement;
use App\Http\Requests\FeeAgreementRequest;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class FeeAgreementController extends Controller
{
    public function store(FeeAgreementRequest $request, Matter $matter): RedirectResponse
    {
        Gate::authorize('bill', $matter);

        $attributes = $request->agreementAttributes();

        DB::transaction(function () use ($matter, $attributes) {
            $matter->feeAgreements()->where('type', $attributes['type'])->where('is_active', true)->update(['is_active' => false]);
            $matter->feeAgreements()->create([...$attributes, 'client_id' => $matter->client_id]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fee agreement recorded.')]);

        return to_route('matters.show', $matter);
    }

    public function destroy(Matter $matter, FeeAgreement $feeAgreement): RedirectResponse
    {
        Gate::authorize('bill', $matter);

        $feeAgreement->update(['is_active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fee agreement withdrawn.')]);

        return to_route('matters.show', $matter);
    }
}
