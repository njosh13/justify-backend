@php
    $firm = $snapshot['firm'] ?? $bill->firm->only(['name', 'kra_pin', 'lsk_firm_number', 'address', 'email', 'phone', 'vat_registered']);
    $client = $snapshot['client'] ?? $bill->client->only(['full_name', 'kra_pin', 'address', 'email', 'is_withholding_agent', 'is_vat_exempt', 'vat_exemption_reference']);
    $matter = $snapshot['matter'] ?? $bill->matter->only(['title', 'reference', 'court_level', 'cause_number']);
    $money = fn (?int $cents) => $cents === null ? '' : 'KES '.number_format($cents / 100, 2);
@endphp
@if ($bill->isDraft())
    <div class="draft">DRAFT</div>
@endif
<table class="header">
    <tr>
        <td style="width: 55%">
            <h1>{{ $firm['name'] }}</h1>
            <div class="small muted">
                @if (!empty($firm['address'])){!! nl2br(e($firm['address'])) !!}<br>@endif
                @if (!empty($firm['email'])){{ $firm['email'] }}@endif @if (!empty($firm['phone'])) · {{ $firm['phone'] }}@endif<br>
                @if (!empty($firm['kra_pin']))PIN {{ $firm['kra_pin'] }}@endif
                @if (!empty($firm['lsk_firm_number'])) · LSK {{ $firm['lsk_firm_number'] }}@endif
            </div>
        </td>
        <td class="right">
            <div style="font-size: 13pt; font-weight: bold; text-transform: uppercase; letter-spacing: .08em">{{ $title }}</div>
            <table class="meta" style="margin-left: auto">
                <tr><td class="muted">Number</td><td class="right"><strong>{{ $bill->number ?? 'DRAFT' }}</strong></td></tr>
                <tr><td class="muted">Date</td><td class="right">{{ ($bill->issued_at ?? $bill->created_at)?->format('j F Y') }}</td></tr>
                <tr><td class="muted">Basis</td><td class="right">{{ $bill->cost_basis->value === 'party_party' ? 'Party and party' : 'Advocate and client' }}</td></tr>
                <tr><td class="muted">Order</td><td class="right">{{ $snapshot['aro_version'] ?? $bill->version->code }}</td></tr>
            </table>
        </td>
    </tr>
</table>
<table class="meta" style="width: 100%">
    <tr>
        <td style="width: 50%">
            <div class="small muted">To</div>
            <strong>{{ $client['full_name'] }}</strong><br>
            @if (!empty($client['address'])){!! nl2br(e($client['address'])) !!}<br>@endif
            @if (!empty($client['kra_pin']))<span class="small">PIN {{ $client['kra_pin'] }}</span>@endif
        </td>
        <td>
            <div class="small muted">Matter</div>
            <strong>{{ $matter['title'] }}</strong><br>
            @if (!empty($matter['reference']))<span class="small">Our ref {{ $matter['reference'] }}</span>@endif
            @if (!empty($matter['cause_number']))<span class="small"> · {{ $matter['cause_number'] }}</span>@endif
        </td>
    </tr>
</table>
