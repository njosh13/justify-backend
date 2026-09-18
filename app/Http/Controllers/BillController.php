<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Aro\Engine\CostBasis;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillEvent;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\BillAssembler;
use App\Domain\Billing\Services\InterestCalculator;
use App\Enums\BillType;
use App\Http\Requests\StoreBillRequest;
use App\Models\Matter;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class BillController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Bill::class);

        $status = (string) $request->query('status', '');

        return Inertia::render('bills/index', [
            'bills' => Bill::query()
                ->with(['client:id,full_name', 'matter:id,title,reference'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->orderByDesc('created_at')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Bill $b) => [
                    'id' => $b->id, 'number' => $b->number, 'type' => $b->type->value, 'status' => $b->status->value,
                    'cost_basis' => $b->cost_basis->value, 'total_cents' => $b->total_cents, 'paid_cents' => $b->paid_cents,
                    'client' => $b->client->full_name, 'matter' => $b->matter->title, 'matter_id' => $b->matter_id,
                    'issued_at' => $b->issued_at?->toDateString(), 'created_at' => $b->created_at?->toDateString(),
                ]),
            'filters' => ['status' => $status],
        ]);
    }

    public function store(StoreBillRequest $request, Matter $matter, BillAssembler $assembler): RedirectResponse
    {
        Gate::authorize('bill', $matter);

        try {
            $bill = $assembler->draft(
                $matter,
                BillType::from($request->input('type')),
                CostBasis::from($request->input('cost_basis')),
                $request->input('item_ids'),
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['item_ids' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft bill assembled. Review it, then issue.')]);

        return to_route('bills.show', $bill);
    }

    public function show(Bill $bill, InterestCalculator $interest): Response
    {
        Gate::authorize('view', $bill);

        $bill->load(['client', 'matter', 'version', 'lines', 'events.user', 'payments', 'issuer']);

        $accrued = $bill->delivered_at === null ? 0 : $interest->accrued(
            Money::ofMinor($bill->total_cents, 'KES'),
            CarbonImmutable::instance($bill->delivered_at),
            $bill->interest_claimed_at === null ? null : CarbonImmutable::instance($bill->interest_claimed_at),
            $bill->paid_in_full_at === null ? null : CarbonImmutable::instance($bill->paid_in_full_at),
            CarbonImmutable::now(),
        )->getMinorAmount()->toInt();

        return Inertia::render('bills/show', [
            'bill' => [
                'id' => $bill->id, 'number' => $bill->number, 'type' => $bill->type->value, 'type_label' => $bill->type->label(),
                'status' => $bill->status->value, 'cost_basis' => $bill->cost_basis->value,
                'fees_cents' => $bill->fees_cents, 'recharges_cents' => $bill->recharges_cents,
                'disbursements_cents' => $bill->disbursements_cents, 'vat_cents' => $bill->vat_cents,
                'wht_expected_cents' => $bill->wht_expected_cents, 'total_cents' => $bill->total_cents,
                'paid_cents' => $bill->paid_cents, 'outstanding_cents' => $bill->outstandingCents(),
                'issued_at' => $bill->issued_at?->toDateTimeString(), 'issued_by' => $bill->issuer?->name,
                'delivered_at' => $bill->delivered_at?->toDateTimeString(), 'delivery_method' => $bill->delivery_method,
                'deemed_agreed_at' => $bill->deemed_agreed_at?->toDateString(),
                'interest_claimed_at' => $bill->interest_claimed_at?->toDateString(),
                'paid_in_full_at' => $bill->paid_in_full_at?->toDateString(),
                'locked_at' => $bill->locked_at?->toDateTimeString(), 'pdf_path' => $bill->pdf_path,
                'interest_accrued_cents' => $accrued,
                'aro_version' => $bill->version->only(['code', 'legal_notice', 'status']),
                'client' => $bill->client->only(['id', 'full_name', 'kra_pin', 'is_withholding_agent', 'is_vat_exempt']),
                'matter' => $bill->matter->only(['id', 'title', 'reference', 'cause_number']),
                'created_at' => $bill->created_at?->toDateTimeString(),
            ],
            'lines' => $bill->lines->map(fn (BillLine $l) => [
                'id' => $l->id, 'seq' => $l->seq, 'dated_on' => $l->dated_on?->toDateString(), 'particulars' => $l->particulars,
                'claimed_cents' => $l->claimed_cents, 'taxed_off_cents' => $l->taxed_off_cents, 'rule_reference' => $l->rule_reference,
                'provenance' => $l->provenance, 'section' => $l->section, 'vat_rate' => (float) $l->vat_rate,
                'vat_cents' => $l->vat_cents, 'tax_type_code' => $l->tax_type_code,
            ]),
            'events' => $bill->events->map(fn (BillEvent $e) => [
                'id' => $e->id, 'type' => $e->type, 'user' => $e->user?->name, 'payload' => $e->payload,
                'created_at' => $e->created_at->toDateTimeString(),
            ]),
            'payments' => $bill->payments->map(fn (Payment $p) => [
                'id' => $p->id, 'amount_cents' => $p->amount_cents, 'method' => $p->method, 'reference' => $p->reference,
                'received_at' => $p->received_at->toDateString(), 'allocated_to' => $p->allocated_to,
                'wht_certificate_reference' => $p->wht_certificate_reference,
            ]),
            'can' => [
                'issue' => Gate::allows('issue', $bill),
                'delete' => Gate::allows('delete', $bill),
                'deliver' => Gate::allows('deliver', $bill),
                'claimInterest' => Gate::allows('claimInterest', $bill) && $bill->interest_claimed_at === null && $bill->paid_in_full_at === null,
                'recordPayment' => Gate::allows('recordPayment', $bill),
            ],
        ]);
    }

    public function destroy(Bill $bill): RedirectResponse
    {
        Gate::authorize('delete', $bill);

        $matterId = $bill->matter_id;

        DB::transaction(function () use ($bill) {
            $bill->chargeableItems()->update(['bill_id' => null]);
            $bill->lines()->delete();
            $bill->events()->delete();
            $bill->forceDelete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft discarded; its lines are unbilled again.')]);

        return to_route('matters.show', $matterId);
    }
}
