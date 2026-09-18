<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Firm;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Resolves the firm the current request acts for. Every firm-scoped model
 * reads its id through FirmScope, so a request never sees another firm's rows.
 *
 * Resolution: an explicit override (tests, console) → the authenticated
 * user's `current_firm_id` when they are still a member → their first
 * membership. Null when nobody is authenticated or they belong to no firm.
 */
final class CurrentFirm
{
    private ?Firm $firm = null;

    private bool $resolved = false;

    public function __construct(private readonly AuthFactory $auth) {}

    public function get(): ?Firm
    {
        if (! $this->resolved) {
            $this->firm = $this->resolve();
            $this->resolved = true;
        }

        return $this->firm;
    }

    public function id(): ?string
    {
        return $this->get()?->id;
    }

    public function set(?Firm $firm): void
    {
        $this->firm = $firm;
        $this->resolved = true;
    }

    public function forget(): void
    {
        $this->firm = null;
        $this->resolved = false;
    }

    private function resolve(): ?Firm
    {
        $user = $this->auth->guard()->user();

        if (! $user instanceof User) {
            return null;
        }

        return $user->resolveCurrentFirm();
    }
}
