<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Aro\Models\AroVersion;
use App\Domain\Aro\Services\AroPublisher;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

final class ReviewedAroVersionController extends Controller
{
    public function store(Request $request, AroVersion $aroVersion, AroPublisher $publisher): RedirectResponse
    {
        Gate::authorize('review', $aroVersion);

        try {
            $publisher->markReviewed($aroVersion, $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['review' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':code marked as reviewed. A different user must publish it.', ['code' => $aroVersion->code])]);

        return to_route('admin.aro.index');
    }
}
