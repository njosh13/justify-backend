<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Policies\Concerns\ResolvesFirmRole;

final class ClientPolicy
{
    use ResolvesFirmRole;

    public function viewAny(User $user): bool
    {
        return $this->role($user) !== null;
    }

    public function view(User $user, Client $client): bool
    {
        return $this->role($user) !== null;
    }

    public function create(User $user): bool
    {
        return (bool) $this->role($user)?->canWrite();
    }

    public function update(User $user, Client $client): bool
    {
        return (bool) $this->role($user)?->canWrite();
    }
}
