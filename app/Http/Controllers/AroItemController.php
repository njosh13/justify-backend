<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Aro\Models\AroVersion;
use App\Domain\Aro\Services\AroCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Plan §10 `GET /aro/items?schedule=&q=`: the searchable catalogue. */
final class AroItemController extends Controller
{
    public function index(Request $request, AroCatalogue $catalogue): JsonResponse
    {
        $version = $request->filled('aro_version_id')
            ? AroVersion::query()->whereKey($request->input('aro_version_id'))->firstOrFail()
            : AroVersion::current();

        if ($version === null) {
            return response()->json(['version' => null, 'items' => []]);
        }

        return response()->json([
            'version' => $version->only(['id', 'code', 'legal_notice', 'status']),
            'items' => $catalogue->items(
                $version,
                $request->filled('schedule') ? (int) $request->input('schedule') : null,
                $request->input('q'),
                ! $request->boolean('include_inactive'),
            ),
        ]);
    }
}
