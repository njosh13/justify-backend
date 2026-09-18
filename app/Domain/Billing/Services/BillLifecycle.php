<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Payment;
use App\Enums\BillStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Post-issue transitions: delivery (starts the para 6/7 clocks), payments. */
final class BillLifecycle
{
    public function deliver(Bill $bill, User $user, string $method, ?Carbon $at = null): Bill
    {
        if ($bill->isDraft()) {
            throw new InvalidArgumentException('Issue the bill before recording delivery');
        }
        if ($bill->delivered_at !== null) {
            throw new InvalidArgumentException('Delivery has already been recorded');
        }

        $at ??= now();

        $bill->forceFill([
            'status' => BillStatus::Delivered,
            'delivered_at' => $at,
            'delivery_method' => $method,
            'deemed_agreed_at' => $at->toImmutable()->addMonthNoOverflow(),
        ])->save();

        $bill->recordEvent('delivered', $user, ['method' => $method, 'at' => $at->toIso8601String()]);

        return $bill;
    }

    public function claimInterest(Bill $bill, User $user): Bill
    {
        if ($bill->delivered_at === null) {
            throw new InvalidArgumentException('Interest under para 7 runs from delivery; record delivery first');
        }
        if ($bill->paid_in_full_at !== null) {
            throw new InvalidArgumentException('Para 7: interest cannot be claimed after the bill has been paid in full');
        }

        $bill->forceFill(['interest_claimed_at' => now()])->save();
        $bill->recordEvent('interest_claimed', $user);

        return $bill;
    }

    /** @param array{amount_cents:int, method:string, reference?:string|null, received_at:string, allocated_to?:string, wht_certificate_reference?:string|null, notes?:string|null} $data */
    public function recordPayment(Bill $bill, User $user, array $data): Payment
    {
        if ($bill->isDraft()) {
            throw new InvalidArgumentException('A draft bill cannot receive payments');
        }

        return DB::transaction(function () use ($bill, $user, $data) {
            $payment = $bill->payments()->create([
                'firm_id' => $bill->firm_id,
                'amount_cents' => $data['amount_cents'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'received_at' => $data['received_at'],
                'allocated_to' => $data['allocated_to'] ?? 'fees',
                'wht_certificate_reference' => $data['wht_certificate_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
            ]);

            $paid = (int) $bill->payments()->sum('amount_cents');
            $settled = $paid >= $bill->total_cents;

            $bill->forceFill([
                'paid_cents' => $paid,
                'status' => $settled ? BillStatus::Paid : BillStatus::PartiallyPaid,
                'paid_in_full_at' => $settled ? ($bill->paid_in_full_at ?? now()) : null,
            ])->save();

            $bill->recordEvent('payment_received', $user, ['amount_cents' => $data['amount_cents'], 'method' => $data['method']]);

            return $payment;
        });
    }
}
