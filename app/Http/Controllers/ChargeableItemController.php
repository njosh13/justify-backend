<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\Models\ChargeableItem;
use App\Domain\Billing\Services\ChargeableItemWriter;
use App\Http\Requests\ChargeableItemRequest;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ChargeableItemController extends Controller
{
    public function store(ChargeableItemRequest $request, Matter $matter, ChargeableItemWriter $writer): RedirectResponse
    {
        Gate::authorize('bill', $matter);

        $writer->store($matter, $request->itemAttributes(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Line added.')]);

        return to_route('matters.show', $matter);
    }

    public function update(ChargeableItemRequest $request, Matter $matter, ChargeableItem $chargeableItem, ChargeableItemWriter $writer): RedirectResponse
    {
        Gate::authorize('update', $chargeableItem);

        $writer->update($chargeableItem, $request->itemAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Line updated.')]);

        return to_route('matters.show', $matter);
    }

    public function destroy(Matter $matter, ChargeableItem $chargeableItem): RedirectResponse
    {
        Gate::authorize('delete', $chargeableItem);

        $chargeableItem->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Line removed.')]);

        return to_route('matters.show', $matter);
    }
}
