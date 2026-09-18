<?php

namespace App\Http\Middleware;

use App\Models\Firm;
use App\Tenancy\CurrentFirm;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $firm = $user === null ? null : app(CurrentFirm::class)->get();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'firm' => $firm === null ? null : [
                    'id' => $firm->id,
                    'name' => $firm->name,
                    'vat_registered' => $firm->vat_registered,
                    'default_cost_basis' => $firm->default_cost_basis,
                    'rounding_policy' => $firm->rounding_policy,
                    'role' => $user->roleIn($firm)?->value,
                ],
                'firms' => $user === null ? [] : $user->firms()->orderBy('name')->get()->map(fn (Firm $f) => ['id' => $f->id, 'name' => $f->name])->all(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
