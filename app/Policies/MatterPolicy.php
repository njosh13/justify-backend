<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Matter;
use App\Models\User;
use App\Policies\Concerns\ResolvesFirmRole;

final class MatterPolicy
{
    use ResolvesFirmRole;

    public function viewAny(User $user): bool
    {
        return $this->role($user) !== null;
    }

    public function view(User $user, Matter $matter): bool
    {
        return $this->role($user) !== null;
    }

    public function create(User $user): bool
    {
        return (bool) $this->role($user)?->canWrite();
    }

    public function update(User $user, Matter $matter): bool
    {
        return (bool) $this->role($user)?->canWrite();
    }

    /** Chargeable work, classification, fee agreements and bills on the matter. */
    public function bill(User $user, Matter $matter): bool
    {
        return (bool) $this->role($user)?->canBill();
    }
}
