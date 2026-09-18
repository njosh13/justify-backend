<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $bill->number ?? 'Draft fee note' }}</title>
    @include('bills.partials.style')
</head>
<body>
@php
    $firm = $snapshot['firm'] ?? $bill->firm->only(['name', 'kra_pin', 'vat_registered']);
    $client = $snapshot['client'] ?? $bill->client->only(['is_withholding_agent', 'is_vat_exempt', 'vat_exemption_reference']);
    $money = fn (?int $cents) => $cents === null ? '' : number_format($cents / 100, 2);
    $fees = $bill->lines->where('section', 'fees');
    $disbursements = $bill->lines->where('section', 'disbursements');
    $vatApplies = (bool) ($firm['vat_registered'] ?? false);
@endphp
@include('bills.partials.header', ['title' => $vatApplies && !($client['is_vat_exempt'] ?? false) ? 'Tax invoice / fee note' : 'Fee note'])

<h2>Professional fees</h2>
<table class="lines">
    <thead>
        <tr>
            <th class="date">Date</th>
            <th>Particulars</th>
            @if ($vatApplies)<th class="center" style="width: 12mm">Tax</th>@endif
            <th class="amount">KES</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($fees as $line)
            <tr>
                <td class="date">{{ $line->dated_on?->format('d/m/Y') }}</td>
                <td>
                    {{ $line->particulars }}
                    @if ($line->rule_reference)<div class="prov">{{ $line->rule_reference }}@if ($line->provenance) — {{ $line->provenance }}@endif</div>@endif
                </td>
                @if ($vatApplies)<td class="center">{{ $line->tax_type_code }}</td>@endif
                <td class="amount">{{ $money($line->claimed_cents) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if ($disbursements->isNotEmpty())
    <h2>Disbursements (paid as agent, receipts available)</h2>
    <table class="lines">
        <tbody>
            @foreach ($disbursements as $line)
                <tr>
                    <td class="date">{{ $line->dated_on?->format('d/m/Y') }}</td>
                    <td>{{ $line->particulars }}</td>
                    @if ($vatApplies)<td class="center" style="width: 12mm">{{ $line->tax_type_code }}</td>@endif
                    <td class="amount">{{ $money($line->claimed_cents) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<table class="totals">
    <tr><td>Professional fees</td><td class="right">{{ $money($bill->fees_cents) }}</td></tr>
    @if ($bill->recharges_cents > 0)<tr><td>Recharges</td><td class="right">{{ $money($bill->recharges_cents) }}</td></tr>@endif
    @if ($vatApplies)
        <tr><td>VAT @ 16% on fees and recharges{{ ($client['is_vat_exempt'] ?? false) ? ' (exempt: '.($client['vat_exemption_reference'] ?? '').')' : '' }}</td><td class="right">{{ $money($bill->vat_cents) }}</td></tr>
    @endif
    @if ($bill->disbursements_cents > 0)<tr><td>Disbursements</td><td class="right">{{ $money($bill->disbursements_cents) }}</td></tr>@endif
    <tr class="grand"><td>Total due</td><td class="right">KES {{ $money($bill->total_cents) }}</td></tr>
</table>

@if (($client['is_withholding_agent'] ?? false) && $bill->wht_expected_cents > 0)
    <div class="box small">
        <strong>Withholding tax.</strong> Where you are a withholding agent, withhold KES {{ $money($bill->wht_expected_cents) }}
        (5% of professional fees and recharges of KES {{ $money($bill->fees_cents + $bill->recharges_cents) }}) and remit it to KRA against our PIN {{ $firm['kra_pin'] ?? '' }};
        pay the balance of KES {{ $money($bill->total_cents - $bill->wht_expected_cents) }} and forward the withholding certificate.
    </div>
@endif

<div class="foot">
    Rendered under the Advocates (Remuneration) Order ({{ $snapshot['aro_version'] ?? $bill->version->code }}).
    Interest at 14% per annum may be charged on this bill from one month after delivery (para 7).
    Costs are deemed agreed one calendar month from delivery unless disputed or referred for taxation (para 6).
</div>
</body>
</html>
