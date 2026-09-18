<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\User;
use App\Tenancy\CurrentFirm;

trait ResolvesFirmRole
{
    /** The user's role in the firm the request acts for (null when not a member). */
    protected function role(User $user, ?Firm $firm = null): ?FirmRole
    {
        $firm ??= app(CurrentFirm::class)->get();

        return $firm === null ? null : $user->roleIn($firm);
    }
}
