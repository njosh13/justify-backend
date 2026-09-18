<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\Bill;
use App\Models\User;
use App\Policies\Concerns\ResolvesFirmRole;

final class BillPolicy
{
    use ResolvesFirmRole;

    public function viewAny(User $user): bool
    {
        return $this->role($user) !== null;
    }

    public function view(User $user, Bill $bill): bool
    {
        return $this->role($user) !== null;
    }

    public function issue(User $user, Bill $bill): bool
    {
        return (bool) $this->role($user)?->canBill() && $bill->isDraft();
    }

    public function delete(User $user, Bill $bill): bool
    {
        return (bool) $this->role($user)?->canBill() && $bill->isDraft();
    }

    public function deliver(User $user, Bill $bill): bool
    {
        return (bool) $this->role($user)?->canBill() && ! $bill->isDraft();
    }

    public function claimInterest(User $user, Bill $bill): bool
    {
        return (bool) $this->role($user)?->canBill() && $bill->delivered_at !== null;
    }

    public function recordPayment(User $user, Bill $bill): bool
    {
        return (bool) $this->role($user)?->canRecordPayments() && ! $bill->isDraft();
    }
}
