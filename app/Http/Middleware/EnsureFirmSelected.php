<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\CurrentFirm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sends a member of no firm to onboarding before any firm-scoped page. */
final class EnsureFirmSelected
{
    public function __construct(private readonly CurrentFirm $currentFirm) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->currentFirm->get() === null) {
            return redirect()->route('firms.create');
        }

        return $next($request);
    }
}
