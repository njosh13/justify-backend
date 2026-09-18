<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Aro\Models\AroItem;
use App\Domain\Aro\Models\AroVersion;
use App\Domain\Billing\Models\FeeAgreement;
use App\Domain\Billing\Models\MatterClassification;
use App\Domain\Billing\Services\ChargeableItemWriter;
use App\Enums\CourtLevel;
use App\Enums\FirmRole;
use App\Models\Client;
use App\Models\Firm;
use App\Models\Matter;
use App\Models\User;
use App\Tenancy\CurrentFirm;
use Illuminate\Database\Seeder;

/**
 * A firm, two users (reviewer + publisher for the two-person gate), clients
 * and matters with priced work — enough to walk the whole billing flow.
 * Local development only; the published ARO version is force-published.
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password', 'email_verified_at' => now()],
        );
        $partner = User::query()->firstOrCreate(
            ['email' => 'partner@example.com'],
            ['name' => 'Second Partner', 'password' => 'password', 'email_verified_at' => now()],
        );

        $firm = Firm::query()->firstOrCreate(
            ['name' => 'Kimondo & Co. Advocates'],
            ['kra_pin' => 'P051234567X', 'lsk_firm_number' => 'LSK/F/1234', 'address' => "Reinsurance Plaza, 4th Floor\nTaifa Road, Nairobi", 'email' => 'accounts@kimondo.co.ke', 'phone' => '+254 700 000 000', 'vat_registered' => true, 'bill_number_prefix' => 'KC'],
        );
        $firm->users()->syncWithoutDetaching([$owner->id => ['role' => FirmRole::Owner->value], $partner->id => ['role' => FirmRole::Admin->value]]);
        $owner->forceFill(['current_firm_id' => $firm->id])->save();
        $partner->forceFill(['current_firm_id' => $firm->id])->save();

        app(CurrentFirm::class)->set($firm);

        $version = AroVersion::current();
        if ($version !== null && ! $version->isPublished()) {
            $version->forceFill(['status' => AroVersion::STATUS_REVIEWED, 'reviewed_by' => $owner->id, 'reviewed_at' => now(), 'published_by' => $partner->id, 'published_at' => now()])->save();
            $version->forceFill(['status' => AroVersion::STATUS_PUBLISHED])->save();
        }

        if (Client::query()->exists()) {
            return;
        }

        $wanjiku = Client::create(['full_name' => 'Wanjiku Njeri', 'client_type' => 'individual', 'id_number' => '12345678', 'email' => 'wanjiku@example.com', 'phone' => '+254 711 111 111']);
        $acme = Client::create(['full_name' => 'Acme Holdings Ltd', 'client_type' => 'company', 'kra_pin' => 'P000111222Y', 'email' => 'legal@acme.co.ke', 'address' => 'Westlands, Nairobi', 'is_withholding_agent' => true]);

        $writer = app(ChargeableItemWriter::class);

        $sale = Matter::create(['client_id' => $wanjiku->id, 'title' => 'Purchase of LR No. 209/1234, Kileleshwa', 'reference' => 'CONV/2026/014', 'court_level' => CourtLevel::None, 'value_cents' => 10_000_000_00, 'opened_on' => now()->subDays(20)->toDateString()]);
        MatterClassification::factory()->head('S1.SALE', 10_000_000_00)->create(['matter_id' => $sale->id, 'firm_id' => $firm->id, 'basis_limb' => 'deed_price']);
        $writer->store($sale, ['kind' => 'fee', 'aro_item_id' => aroItem('S1.SALE'), 'description' => 'Instructions, investigation of title, preparation of transfer, completion and registration', 'occurred_on' => now()->subDays(10)->toDateString(), 'entered_cents' => 175_000_00, 'modifier_codes' => [], 'modifier_amounts' => []], $owner);
        $writer->store($sale, ['kind' => 'disbursement', 'description' => 'Stamp duty (4%)', 'occurred_on' => now()->subDays(8)->toDateString(), 'entered_cents' => 400_000_00, 'modifier_codes' => [], 'modifier_amounts' => []], $owner);
        $writer->store($sale, ['kind' => 'disbursement', 'description' => 'Registration fees and searches', 'occurred_on' => now()->subDays(6)->toDateString(), 'entered_cents' => 5_500_00, 'modifier_codes' => [], 'modifier_amounts' => []], $owner);
        $writer->store($sale, ['kind' => 'recharge', 'description' => 'Photocopying and courier', 'occurred_on' => now()->subDays(5)->toDateString(), 'entered_cents' => 2_400_00, 'modifier_codes' => [], 'modifier_amounts' => []], $owner);

        $suit = Matter::create(['client_id' => $acme->id, 'title' => 'Acme Holdings Ltd v. Bora Traders — recovery of KES 5,000,000', 'reference' => 'LIT/2026/007', 'court_level' => CourtLevel::HighCourt, 'cause_number' => 'HCCC E123 of 2026', 'value_cents' => 5_000_000_00, 'opened_on' => now()->subDays(60)->toDateString()]);
        MatterClassification::factory()->head('S6.INSTR.DEFENDED', 5_000_000_00)->create(['matter_id' => $suit->id, 'firm_id' => $firm->id, 'basis_limb' => 'sum_sued', 'posture' => 'full_trial']);
        $writer->store($suit, ['kind' => 'fee', 'aro_item_id' => aroItem('S6.INSTR.DEFENDED'), 'description' => 'Instructions to sue — defended', 'occurred_on' => now()->subDays(50)->toDateString(), 'entered_cents' => 300_000_00, 'modifier_codes' => [], 'modifier_amounts' => []], $owner);
        $writer->store($suit, ['kind' => 'fee', 'aro_item_id' => aroItem('S6.GETTING_UP'), 'description' => 'Getting up and preparing for trial', 'occurred_on' => now()->subDays(20)->toDateString(), 'entered_cents' => 150_000_00, 'modifier_codes' => [], 'modifier_amounts' => []], $owner);
        $writer->store($suit, ['kind' => 'fee', 'aro_item_id' => aroItem('S6.DRAWING.PLEADING'), 'description' => 'Drawing plaint (7 folios)', 'occurred_on' => now()->subDays(48)->toDateString(), 'quantity' => 7, 'unit' => 'folio', 'entered_cents' => 2_325_00, 'modifier_codes' => [], 'modifier_amounts' => []], $owner);
        $writer->store($suit, ['kind' => 'fee', 'aro_item_id' => aroItem('S6.ATTEND.COURT.DAY.LOWER'), 'description' => 'Attending court — hearing, whole day', 'occurred_on' => now()->subDays(12)->toDateString(), 'entered_cents' => 15_000_00, 'modifier_codes' => [], 'modifier_amounts' => []], $owner);
        $writer->store($suit, ['kind' => 'disbursement', 'description' => 'Court filing fees', 'occurred_on' => now()->subDays(47)->toDateString(), 'entered_cents' => 20_000_00, 'modifier_codes' => [], 'modifier_amounts' => []], $owner);

        $advice = Matter::create(['client_id' => $acme->id, 'title' => 'Opinion on lease renewal — Ngong Road premises', 'reference' => 'GEN/2026/031', 'court_level' => CourtLevel::None, 'opened_on' => now()->subDays(5)->toDateString()]);
        MatterClassification::factory()->create(['matter_id' => $advice->id, 'firm_id' => $firm->id]);
        FeeAgreement::factory()->hourly(12_000_00)->create(['matter_id' => $advice->id, 'firm_id' => $firm->id, 'client_id' => $acme->id]);
        $writer->store($advice, ['kind' => 'time', 'aro_item_id' => aroItem('S5.HOURLY'), 'description' => 'Research and drafting of opinion', 'occurred_on' => now()->subDays(2)->toDateString(), 'quantity' => 3.5, 'unit' => 'hour', 'entered_cents' => 42_000_00, 'modifier_codes' => [], 'modifier_amounts' => []], $owner);
    }
}

function aroItem(string $code): string
{
    return (string) AroItem::query()->where('aro_version_id', AroVersion::current()?->id)->where('code', $code)->value('id');
}
