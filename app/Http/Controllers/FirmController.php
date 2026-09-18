<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FirmRole;
use App\Http\Requests\FirmRequest;
use App\Models\Firm;
use App\Models\User;
use App\Tenancy\CurrentFirm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class FirmController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('firms/create', [
            'hasFirm' => $request->user()->firms()->exists(),
        ]);
    }

    public function store(FirmRequest $request, CurrentFirm $currentFirm): RedirectResponse
    {
        $user = $request->user();

        $firm = DB::transaction(function () use ($request, $user) {
            $firm = Firm::create($request->validated());
            $firm->users()->attach($user->id, ['role' => FirmRole::Owner->value]);
            $user->forceFill(['current_firm_id' => $firm->id])->save();

            return $firm;
        });

        $currentFirm->set($firm);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Firm created. You are its owner.')]);

        return to_route('dashboard');
    }

    public function edit(CurrentFirm $currentFirm): Response
    {
        $firm = $currentFirm->get();
        Gate::authorize('update', $firm);

        return Inertia::render('settings/firm', [
            'firm' => $firm->only(['id', 'name', 'kra_pin', 'lsk_firm_number', 'address', 'email', 'phone', 'vat_registered', 'rounding_policy', 'default_cost_basis', 'bill_number_prefix', 'bill_sequence']),
            'members' => $firm->users()->orderBy('name')->get()->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->getRelationValue('pivot')?->getAttribute('role'),
            ]),
        ]);
    }

    public function update(FirmRequest $request, CurrentFirm $currentFirm): RedirectResponse
    {
        $firm = $currentFirm->get();
        Gate::authorize('update', $firm);

        $firm->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Firm settings saved.')]);

        return to_route('firm.edit');
    }
}
