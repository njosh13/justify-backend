<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Aro\Models\AroVersion;
use App\Domain\Aro\Services\AroCatalogue;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\ChargeableItem;
use App\Domain\Billing\Models\FeeAgreement;
use App\Domain\Billing\Services\ShortfallCalculator;
use App\Enums\CourtLevel;
use App\Http\Requests\MatterRequest;
use App\Models\Client;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class MatterController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Matter::class);

        $search = trim((string) $request->query('q', ''));

        return Inertia::render('matters/index', [
            'matters' => Matter::query()
                ->with('client:id,full_name')
                ->withCount(['chargeableItems as unbilled_count' => fn ($q) => $q->unbilled()])
                ->when($search !== '', fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($search).'%']))
                ->orderByDesc('created_at')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Matter $m) => [
                    'id' => $m->id, 'title' => $m->title, 'reference' => $m->reference, 'court_level' => $m->court_level->value,
                    'court_level_label' => $m->court_level->label(), 'status' => $m->status, 'client' => $m->client->full_name,
                    'value_cents' => $m->value_cents, 'unbilled_count' => $m->unbilled_count,
                ]),
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Matter::class);

        return Inertia::render('matters/create', [
            'clients' => Client::query()->orderBy('full_name')->get(['id', 'full_name']),
            'courtLevels' => self::courtLevels(),
        ]);
    }

    public function store(MatterRequest $request): RedirectResponse
    {
        Gate::authorize('create', Matter::class);

        $matter = Matter::create($request->matterAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Matter opened.')]);

        return to_route('matters.show', $matter);
    }

    public function show(Matter $matter, AroCatalogue $catalogue, ShortfallCalculator $shortfall): Response
    {
        Gate::authorize('view', $matter);

        $matter->load(['client', 'classification.item', 'classification.version', 'feeAgreements', 'chargeableItems.aroItem', 'bills']);
        $version = $matter->classification->version ?? AroVersion::current();

        return Inertia::render('matters/show', [
            'matter' => [
                'id' => $matter->id, 'title' => $matter->title, 'reference' => $matter->reference,
                'court_level' => $matter->court_level->value, 'court_level_label' => $matter->court_level->label(),
                'schedule' => $matter->court_level->schedule(), 'cause_number' => $matter->cause_number,
                'description' => $matter->description, 'value_cents' => $matter->value_cents, 'status' => $matter->status,
                'opened_on' => $matter->opened_on?->toDateString(),
                'client' => $matter->client->only(['id', 'full_name', 'kra_pin', 'is_withholding_agent', 'is_vat_exempt']),
            ],
            'classification' => $matter->classification === null ? null : [
                'aro_item_id' => $matter->classification->aro_item_id,
                'item' => $matter->classification->item?->only(['id', 'code', 'label', 'rule_reference']),
                'basis_cents' => $matter->classification->basis_cents,
                'basis_limb' => $matter->classification->basis_limb,
                'scale' => $matter->classification->scale,
                'posture' => $matter->classification->posture?->value,
                'certificates' => $matter->classification->certificates,
                'contested' => $matter->classification->contested,
                'is_exempt' => $matter->classification->is_exempt,
                'exemption_reason' => $matter->classification->exemption_reason,
            ],
            'feeAgreements' => $matter->feeAgreements->where('is_active', true)->values()->map(fn (FeeAgreement $a) => [
                'id' => $a->id, 'type' => $a->type->value, 'type_label' => $a->type->label(),
                'hourly_rate_cents' => $a->hourly_rate_cents, 'fixed_amount_cents' => $a->fixed_amount_cents,
                'election_communicated_at' => $a->election_communicated_at?->toDateString(),
                'signed_at' => $a->signed_at?->toDateString(), 'notes' => $a->notes,
            ]),
            'items' => $matter->chargeableItems->map(fn (ChargeableItem $i) => [
                'id' => $i->id, 'kind' => $i->kind->value, 'description' => $i->description,
                'occurred_on' => $i->occurred_on->toDateString(), 'quantity' => $i->quantity === null ? null : (float) $i->quantity,
                'unit' => $i->unit, 'entered_cents' => $i->entered_cents, 'computed_minimum_cents' => $i->computed_minimum_cents,
                'computed_bound' => $i->computed_bound, 'computed_ceiling_cents' => $i->computed_ceiling_cents,
                'shortfall_cents' => $i->shortfallCents(), 'uplift_justification' => $i->uplift_justification,
                'is_billable' => $i->is_billable, 'bill_id' => $i->bill_id,
                'aro_item' => $i->aroItem?->only(['id', 'code', 'label', 'rule_reference']),
                'aro_item_id' => $i->aro_item_id,
                'basis_override_cents' => $i->basis_override_cents, 'scale_override' => $i->scale_override,
                'posture_override' => $i->posture_override?->value, 'modifier_codes' => $i->modifier_codes ?? [],
                'modifier_amounts' => $i->modifier_amounts ?? [],
                'snapshot' => $i->computed_snapshot,
            ]),
            'shortfall' => $shortfall->forMatter($matter),
            'bills' => $matter->bills->sortByDesc('created_at')->values()->map(fn (Bill $b) => [
                'id' => $b->id, 'number' => $b->number, 'type' => $b->type->value, 'status' => $b->status->value,
                'cost_basis' => $b->cost_basis->value, 'total_cents' => $b->total_cents, 'created_at' => $b->created_at?->toDateString(),
            ]),
            'catalogue' => $version === null ? [] : $catalogue->items($version, activeOnly: true),
            'aroVersion' => $version?->only(['id', 'code', 'legal_notice', 'status']),
            'courtLevels' => self::courtLevels(),
            'can' => [
                'bill' => Gate::allows('bill', $matter),
                'update' => Gate::allows('update', $matter),
            ],
        ]);
    }

    public function edit(Matter $matter): Response
    {
        Gate::authorize('update', $matter);

        return Inertia::render('matters/edit', [
            'matter' => [
                ...$matter->only(['id', 'client_id', 'title', 'reference', 'cause_number', 'description', 'status']),
                'court_level' => $matter->court_level->value,
                'value' => $matter->value_cents === null ? null : $matter->value_cents / 100,
                'opened_on' => $matter->opened_on?->toDateString(),
            ],
            'clients' => Client::query()->orderBy('full_name')->get(['id', 'full_name']),
            'courtLevels' => self::courtLevels(),
        ]);
    }

    public function update(MatterRequest $request, Matter $matter): RedirectResponse
    {
        Gate::authorize('update', $matter);

        $matter->update($request->matterAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Matter updated.')]);

        return to_route('matters.show', $matter);
    }

    /** @return list<array{value:string, label:string, schedule:int|null}> */
    public static function courtLevels(): array
    {
        return array_map(fn (CourtLevel $c) => ['value' => $c->value, 'label' => $c->label(), 'schedule' => $c->schedule()], CourtLevel::cases());
    }
}
