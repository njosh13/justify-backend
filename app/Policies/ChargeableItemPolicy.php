<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\ChargeableItem;
use App\Models\User;
use App\Policies\Concerns\ResolvesFirmRole;

final class ChargeableItemPolicy
{
    use ResolvesFirmRole;

    public function update(User $user, ChargeableItem $item): bool
    {
        return (bool) $this->role($user)?->canBill() && ! $item->isBilled();
    }

    public function delete(User $user, ChargeableItem $item): bool
    {
        return (bool) $this->role($user)?->canBill() && ! $item->isBilled();
    }
}
