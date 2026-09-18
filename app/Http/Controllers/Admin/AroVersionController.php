<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Aro\Models\AroInterpretation;
use App\Domain\Aro\Models\AroVersion;
use App\Domain\Aro\Services\AroCatalogue;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class AroVersionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', AroVersion::class);

        $users = User::query()->whereIn('id', AroVersion::query()->pluck('reviewed_by')->merge(AroVersion::query()->pluck('published_by'))->filter()->unique())->get()->keyBy('id');

        return Inertia::render('admin/aro/index', [
            'versions' => AroVersion::query()
                ->withCount(['items', 'modifiers', 'interpretations'])
                ->orderByDesc('effective_from')
                ->get()
                ->map(fn (AroVersion $v) => [
                    ...$v->only(['id', 'code', 'legal_notice', 'status', 'source_url', 'source_sha256']),
                    'effective_from' => $v->effective_from?->toDateString(),
                    'effective_to' => $v->effective_to?->toDateString(),
                    'items_count' => $v->items_count, 'modifiers_count' => $v->modifiers_count,
                    'interpretations_count' => $v->interpretations_count,
                    'reviewed_by' => $v->reviewed_by === null ? null : $users[$v->reviewed_by]?->name,
                    'reviewed_at' => $v->reviewed_at?->toDateTimeString(),
                    'published_by' => $v->published_by === null ? null : $users[$v->published_by]?->name,
                    'published_at' => $v->published_at?->toDateTimeString(),
                    'can' => ['review' => Gate::allows('review', $v), 'publish' => Gate::allows('publish', $v)],
                ]),
        ]);
    }

    public function show(Request $request, AroVersion $aroVersion, AroCatalogue $catalogue): Response
    {
        Gate::authorize('view', $aroVersion);

        return Inertia::render('admin/aro/show', [
            'version' => [
                ...$aroVersion->only(['id', 'code', 'legal_notice', 'status', 'source_url', 'source_sha256']),
                'effective_from' => $aroVersion->effective_from?->toDateString(),
            ],
            'items' => $catalogue->items(
                $aroVersion,
                $request->filled('schedule') ? (int) $request->input('schedule') : null,
                $request->input('q'),
                activeOnly: false,
            ),
            'interpretations' => $aroVersion->interpretations()->orderBy('code')->get()->map(fn (AroInterpretation $i) => $i->only(['id', 'code', 'rule_reference', 'question', 'decision', 'rationale', 'status'])),
            'filters' => ['schedule' => $request->input('schedule'), 'q' => $request->input('q')],
        ]);
    }
}
