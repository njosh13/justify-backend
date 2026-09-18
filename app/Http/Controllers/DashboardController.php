<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\ChargeableItem;
use App\Enums\BillStatus;
use App\Models\Client;
use App\Models\Matter;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $unbilled = ChargeableItem::query()->unbilled()->get(['id', 'matter_id', 'entered_cents', 'computed_minimum_cents', 'computed_bound', 'kind', 'aro_item_id']);

        $shortfallMatters = $unbilled
            ->filter(fn (ChargeableItem $i) => $i->shortfallCents() > 0)
            ->pluck('matter_id')->unique()->count();

        $byStatus = Bill::query()
            ->selectRaw('status, count(*) as n, coalesce(sum(total_cents), 0) as total_cents, coalesce(sum(total_cents - paid_cents), 0) as outstanding_cents')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('dashboard', [
            'stats' => [
                'clients' => Client::query()->count(),
                'open_matters' => Matter::query()->where('status', 'open')->count(),
                'unbilled_cents' => (int) $unbilled->sum('entered_cents'),
                'unbilled_items' => $unbilled->count(),
                'shortfall_matters' => $shortfallMatters,
                'bills' => collect(BillStatus::cases())->map(fn (BillStatus $s) => [
                    'status' => $s->value,
                    'count' => (int) ($byStatus[$s->value]->n ?? 0),
                    'total_cents' => (int) ($byStatus[$s->value]->total_cents ?? 0),
                    'outstanding_cents' => (int) ($byStatus[$s->value]->outstanding_cents ?? 0),
                ])->all(),
            ],
            'recentBills' => Bill::query()->with(['client:id,full_name', 'matter:id,title'])->latest()->limit(8)->get()
                ->map(fn (Bill $b) => [
                    'id' => $b->id, 'number' => $b->number, 'status' => $b->status->value, 'type' => $b->type->value,
                    'total_cents' => $b->total_cents, 'client' => $b->client->full_name, 'matter' => $b->matter->title,
                    'created_at' => $b->created_at?->toDateString(),
                ]),
        ]);
    }
}
