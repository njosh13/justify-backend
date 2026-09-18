<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $bill->number ?? 'Draft bill of costs' }}</title>
    @include('bills.partials.style')
</head>
<body>
@php
    $money = fn (?int $cents) => $cents === null ? '' : number_format($cents / 100, 2);
    $fees = $bill->lines->where('section', 'fees');
    $disbursements = $bill->lines->where('section', 'disbursements');
    $taxation = $bill->lines->where('section', 'taxation_attendance');
    $matter = $snapshot['matter'] ?? $bill->matter->only(['title', 'reference', 'court_level', 'cause_number']);
@endphp
@include('bills.partials.header', ['title' => 'Bill of costs'])

<p class="small muted">
    Bill of costs drawn {{ $bill->cost_basis->value === 'party_party' ? 'as between party and party' : 'as between advocate and client' }}
    under the Advocates (Remuneration) Order, prepared in the five columns required by paragraph 69.
    @if (!empty($matter['cause_number'])) {{ $matter['cause_number'] }}.@endif
</p>

<table class="lines">
    <thead>
        <tr>
            <th class="date">Date</th>
            <th class="seq">No.</th>
            <th>Particulars</th>
            <th class="amount">Charges claimed (KES)</th>
            <th class="amount">Taxed off (KES)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($fees as $line)
            <tr>
                <td class="date">{{ $line->dated_on?->format('d/m/Y') }}</td>
                <td class="seq">{{ $line->seq }}</td>
                <td>
                    {{ $line->particulars }}
                    @if ($line->rule_reference)<div class="prov">{{ $line->rule_reference }}@if ($line->provenance) — {{ $line->provenance }}@endif</div>@endif
                </td>
                <td class="amount">{{ $money($line->claimed_cents) }}</td>
                <td class="amount">{{ $line->taxed_off_cents === null ? '' : $money($line->taxed_off_cents) }}</td>
            </tr>
        @endforeach
        <tr class="section"><td colspan="3">Professional charges</td><td class="amount">{{ $money($bill->fees_cents + $bill->recharges_cents) }}</td><td></td></tr>

        @if ($disbursements->isNotEmpty())
            <tr class="section"><td colspan="5">Disbursements (para 69(2))</td></tr>
            @foreach ($disbursements as $line)
                <tr>
                    <td class="date">{{ $line->dated_on?->format('d/m/Y') }}</td>
                    <td class="seq">{{ $line->seq }}</td>
                    <td>{{ $line->particulars }}</td>
                    <td class="amount">{{ $money($line->claimed_cents) }}</td>
                    <td class="amount">{{ $line->taxed_off_cents === null ? '' : $money($line->taxed_off_cents) }}</td>
                </tr>
            @endforeach
            <tr class="section"><td colspan="3">Total disbursements</td><td class="amount">{{ $money($bill->disbursements_cents) }}</td><td></td></tr>
        @endif

        @foreach ($taxation as $line)
            <tr>
                <td class="date"></td>
                <td class="seq">{{ $line->seq }}</td>
                <td>{{ $line->particulars }} <span class="prov">(para 69(3): amount for completion by the taxing officer)</span></td>
                <td class="amount">&nbsp;</td>
                <td class="amount">&nbsp;</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Professional charges</td><td class="right">{{ $money($bill->fees_cents + $bill->recharges_cents) }}</td></tr>
    @if ($bill->vat_cents > 0)<tr><td>VAT @ 16%</td><td class="right">{{ $money($bill->vat_cents) }}</td></tr>@endif
    <tr><td>Disbursements</td><td class="right">{{ $money($bill->disbursements_cents) }}</td></tr>
    <tr class="grand"><td>Total</td><td class="right">KES {{ $money($bill->total_cents) }}</td></tr>
</table>

<div class="foot">
    Drawn under the Advocates (Remuneration) Order ({{ $snapshot['aro_version'] ?? $bill->version->code }}).
    Receipts or vouchers for all disbursements will be produced on taxation if required (para 74).
</div>
</body>
</html>
