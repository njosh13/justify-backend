<?php

declare(strict_types=1);

use App\Domain\Billing\Exceptions\BillLockedException;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\ChargeableItem;
use App\Domain\Billing\Models\MatterClassification;
use App\Enums\BillStatus;
use App\Enums\FirmRole;
use App\Models\Client;
use App\Models\Firm;
use App\Models\Matter;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedPublishedAro();
    Storage::fake('local');
});

/** The §8 worked invoice: fees 100,000, recharges 5,000, disbursements 20,000 for a withholding agent. */
function workedMatter(Firm $firm): Matter
{
    $client = Client::factory()->withholdingAgent()->create(['firm_id' => $firm->id]);
    $matter = Matter::factory()->create(['firm_id' => $firm->id, 'client_id' => $client->id]);
    MatterClassification::factory()->head('S1.SALE', 5_000_000_00)->create(['matter_id' => $matter->id]);

    ChargeableItem::factory()->pricedFee('S1.SALE', 100_000_00, 100_000_00, 5_000_000_00)->create(['matter_id' => $matter->id, 'occurred_on' => '2026-09-01']);
    ChargeableItem::factory()->recharge(5_000_00)->create(['matter_id' => $matter->id, 'occurred_on' => '2026-09-02']);
    ChargeableItem::factory()->disbursement(20_000_00)->create(['matter_id' => $matter->id, 'occurred_on' => '2026-09-03']);

    return $matter;
}

function draftFor(Matter $matter, string $type = 'fee_note', string $basis = 'advocate_client'): Bill
{
    $response = test()->post(route('matters.bills.store', $matter), [
        'type' => $type, 'cost_basis' => $basis, 'item_ids' => $matter->chargeableItems()->pluck('id')->all(),
    ])->assertSessionHasNoErrors();

    return Bill::query()->whereKey(basename((string) $response->headers->get('Location')))->firstOrFail();
}

it('assembles the §8 worked invoice with VAT, WHT memo and sections', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = workedMatter($firm);

    $bill = draftFor($matter);

    expect($bill)
        ->fees_cents->toBe(100_000_00)
        ->recharges_cents->toBe(5_000_00)
        ->disbursements_cents->toBe(20_000_00)
        ->vat_cents->toBe(16_800_00)
        ->total_cents->toBe(141_800_00)
        ->wht_expected_cents->toBe(5_250_00)
        ->status->toBe(BillStatus::Draft);

    $lines = $bill->lines;
    expect($lines)->toHaveCount(3)
        ->and($lines->pluck('section')->all())->toBe(['fees', 'fees', 'disbursements'])
        ->and($lines->pluck('tax_type_code')->all())->toBe(['B', 'B', 'D'])
        ->and($lines[0]->provenance)->toContain('Sch 1, First Scale, para 1')
        ->and($matter->chargeableItems()->unbilled()->count())->toBe(0);
});

it('charges no VAT for a firm that is not VAT registered', function () {
    $firm = Firm::factory()->notVatRegistered()->create();
    actingAsMemberOf($firm);
    $matter = workedMatter($firm);

    $bill = draftFor($matter);

    expect($bill->vat_cents)->toBe(0)
        ->and($bill->total_cents)->toBe(125_000_00)
        ->and($bill->lines->pluck('tax_type_code')->unique()->all())->toBe([null]);
});

it('draws a party-and-party bill of costs at scale with the para 69 taxation line', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = Matter::factory()->highCourt()->create(['firm_id' => $firm->id]);
    MatterClassification::factory()->head('S6.INSTR.DEFENDED', 5_000_000_00)->create(['matter_id' => $matter->id]);
    // Entered on the advocate-client basis (300,000 = 200,000 × 1.5); the party-party bill draws the scale figure.
    ChargeableItem::factory()->pricedFee('S6.INSTR.DEFENDED', 300_000_00, 300_000_00)->create(['matter_id' => $matter->id]);

    $bill = draftFor($matter, 'bill_of_costs', 'party_party');

    expect($bill->fees_cents)->toBe(200_000_00)
        ->and($bill->lines->last()->section)->toBe('taxation_attendance')
        ->and($bill->lines->last()->claimed_cents)->toBe(0)
        ->and($bill->lines->first()->provenance)->toContain('drawn party and party at scale');
});

it('issues a bill: numbers, locks, snapshots, renders the PDF, logs the event', function () {
    $firm = Firm::factory()->create(['bill_number_prefix' => 'KC']);
    $user = actingAsMemberOf($firm);
    $bill = draftFor(workedMatter($firm));

    $this->post(route('bills.issue', $bill))->assertRedirect(route('bills.show', $bill))->assertSessionHasNoErrors();

    $bill->refresh();
    expect($bill->number)->toBe('KC/'.now()->format('Y').'/0001')
        ->and($bill->status)->toBe(BillStatus::Issued)
        ->and($bill->locked_at)->not->toBeNull()
        ->and($bill->issued_by)->toBe($user->id)
        ->and($bill->computed_snapshot['totals']['total_cents'])->toBe(141_800_00)
        ->and($bill->events->pluck('type')->all())->toBe(['drafted', 'issued']);

    Storage::disk('local')->assertExists($bill->pdf_path);
    expect(Storage::disk('local')->get($bill->pdf_path))->toStartWith('%PDF');
});

it('numbers bills sequentially per firm', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);

    $first = draftFor(workedMatter($firm));
    $this->post(route('bills.issue', $first));
    $second = draftFor(workedMatter($firm));
    $this->post(route('bills.issue', $second));

    expect($second->refresh()->number)->toEndWith('/0002');
});

it('refuses to issue while fees fall below scale', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = Matter::factory()->create(['firm_id' => $firm->id]);
    MatterClassification::factory()->head('S1.SALE', 10_000_000_00)->exempt()->create(['matter_id' => $matter->id]);
    ChargeableItem::factory()->pricedFee('S1.SALE', 50_000_00, 175_000_00, 10_000_000_00)->create(['matter_id' => $matter->id]);
    $bill = draftFor($matter);

    // The exemption is withdrawn after drafting: issue must re-check.
    $matter->classification->update(['is_exempt' => false]);

    $this->post(route('bills.issue', $bill))
        ->assertSessionHasErrors('issue');
    expect(sessionError('issue'))->toContain('statutory minimum');

    expect($bill->refresh()->number)->toBeNull();
});

it('refuses to issue on an unpublished ARO version', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $bill = draftFor(workedMatter($firm));
    $bill->version->forceFill(['status' => 'draft'])->save();

    $this->post(route('bills.issue', $bill))
        ->assertSessionHasErrors('issue');
    expect(sessionError('issue'))->toContain('not published');
});

it('locks an issued bill against money changes and deletion', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $bill = draftFor(workedMatter($firm));
    $this->post(route('bills.issue', $bill));
    $bill->refresh();

    expect(fn () => $bill->update(['total_cents' => 1]))->toThrow(BillLockedException::class)
        ->and(fn () => $bill->lines()->first()->update(['claimed_cents' => 1]))->toThrow(BillLockedException::class)
        ->and(fn () => $bill->lines()->first()->delete())->toThrow(BillLockedException::class);

    $this->delete(route('bills.destroy', $bill))->assertForbidden();

    $bill->lines()->first()->update(['taxed_off_cents' => 5_000_00]);
    expect($bill->lines()->first()->taxed_off_cents)->toBe(5_000_00);
});

it('discards a draft and releases its lines', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = workedMatter($firm);
    $bill = draftFor($matter);

    $this->delete(route('bills.destroy', $bill))->assertRedirect(route('matters.show', $matter));

    expect(Bill::query()->count())->toBe(0)
        ->and($matter->chargeableItems()->unbilled()->count())->toBe(3);
});

it('records delivery, the deemed-agreement date, an interest claim and payments', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $bill = draftFor(workedMatter($firm));
    $this->post(route('bills.issue', $bill));

    $this->post(route('bills.deliver', $bill), ['delivery_method' => 'email', 'delivered_at' => '2026-01-31 10:00:00'])->assertSessionHasNoErrors();
    $bill->refresh();
    expect($bill->status)->toBe(BillStatus::Delivered)
        ->and($bill->deemed_agreed_at?->toDateString())->toBe('2026-02-28');

    $this->post(route('bills.claim-interest', $bill))->assertSessionHasNoErrors();
    expect($bill->refresh()->interest_claimed_at)->not->toBeNull();

    $this->post(route('bills.payments.store', $bill), ['amount' => 100_000, 'method' => 'bank', 'received_at' => '2026-03-01'])->assertSessionHasNoErrors();
    expect($bill->refresh()->status)->toBe(BillStatus::PartiallyPaid)->and($bill->paid_cents)->toBe(100_000_00);

    $this->post(route('bills.payments.store', $bill), ['amount' => 41_800, 'method' => 'mpesa', 'received_at' => '2026-03-02'])->assertSessionHasNoErrors();
    expect($bill->refresh()->status)->toBe(BillStatus::Paid)->and($bill->paid_in_full_at)->not->toBeNull();

    $this->get(route('bills.show', $bill))->assertOk();
});

it('lets accounts staff record payments but not issue bills', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $bill = draftFor(workedMatter($firm));
    $this->post(route('bills.issue', $bill));

    actingAsMemberOf($firm, FirmRole::Accounts);
    $this->post(route('bills.payments.store', $bill), ['amount' => 10, 'method' => 'cash', 'received_at' => '2026-03-01'])->assertSessionHasNoErrors();

    $second = Bill::factory()->create(['matter_id' => $bill->matter_id]);
    $this->post(route('bills.issue', $second))->assertForbidden();
});

it('renders the fee note and bill of costs previews with the required parts', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = workedMatter($firm);
    $bill = draftFor($matter);

    $this->get(route('bills.preview', $bill))
        ->assertOk()
        ->assertSee('DRAFT')
        ->assertSee('Withholding tax')
        ->assertSee('141,800.00');

    $matter->chargeableItems()->update(['bill_id' => null]);
    $costs = draftFor($matter, 'bill_of_costs', 'advocate_client');

    $this->get(route('bills.preview', $costs))
        ->assertOk()
        ->assertSeeInOrder(['Date', 'No.', 'Particulars', 'Charges claimed', 'Taxed off'])
        ->assertSeeInOrder(['Professional charges', 'Disbursements (para 69(2))', 'Attending taxation']);
});

it('escapes client-provided text in the document', function () {
    $firm = Firm::factory()->create();
    actingAsMemberOf($firm);
    $matter = workedMatter($firm);
    $matter->client->update(['full_name' => "O'Reilly <script>alert(1)</script>"]);
    $bill = draftFor($matter);

    $this->get(route('bills.preview', $bill))
        ->assertOk()
        ->assertSee('&lt;script&gt;', escape: false)
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});
