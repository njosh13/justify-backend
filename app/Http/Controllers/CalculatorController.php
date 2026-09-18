<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Aro\Models\AroVersion;
use App\Domain\Aro\Services\AroCatalogue;
use Inertia\Inertia;
use Inertia\Response;

/** The fee calculator sandbox: exercise the engine without a matter. */
final class CalculatorController extends Controller
{
    public function index(AroCatalogue $catalogue): Response
    {
        $versions = AroVersion::query()->orderByDesc('effective_from')->get();
        $current = AroVersion::current();

        return Inertia::render('calculator/index', [
            'versions' => $versions->map(fn (AroVersion $v) => $v->only(['id', 'code', 'legal_notice', 'status', 'effective_from'])),
            'currentVersionId' => $current?->id,
            'catalogue' => $current === null ? [] : $catalogue->items($current, activeOnly: false),
        ]);
    }
}
