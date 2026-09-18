<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Aro\Models\AroVersion;
use App\Models\User;
use App\Policies\Concerns\ResolvesFirmRole;

/**
 * The ARO catalogue is global data. Any member may browse it; firm owners and
 * admins may review and publish (the two-person gate lives in AroPublisher).
 */
final class AroVersionPolicy
{
    use ResolvesFirmRole;

    public function viewAny(User $user): bool
    {
        return $this->role($user) !== null;
    }

    public function view(User $user, AroVersion $version): bool
    {
        return $this->role($user) !== null;
    }

    public function review(User $user, AroVersion $version): bool
    {
        return (bool) $this->role($user)?->canManageFirm();
    }

    public function publish(User $user, AroVersion $version): bool
    {
        return (bool) $this->role($user)?->canManageFirm();
    }
}
