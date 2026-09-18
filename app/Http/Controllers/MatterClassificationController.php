<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Aro\Models\AroVersion;
use App\Http\Requests\MatterClassificationRequest;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class MatterClassificationController extends Controller
{
    /** Replaces the active classification; history is kept as inactive rows. */
    public function update(MatterClassificationRequest $request, Matter $matter): RedirectResponse
    {
        Gate::authorize('bill', $matter);

        $version = AroVersion::current();
        abort_if($version === null, 422, 'No ARO version is loaded.');

        $attributes = $request->classificationAttributes();
        if (($attributes['is_exempt'] ?? false) && $matter->classification?->is_exempt !== true) {
            $attributes['exempted_by'] = $request->user()->id;
            $attributes['exempted_at'] = now();
        }

        DB::transaction(function () use ($matter, $version, $attributes) {
            $matter->classifications()->where('is_active', true)->update(['is_active' => false]);
            $matter->classifications()->create([...$attributes, 'aro_version_id' => $version->id, 'is_active' => true]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Classification saved. Existing fee lines keep their stored minimum until edited.')]);

        return to_route('matters.show', $matter);
    }
}
