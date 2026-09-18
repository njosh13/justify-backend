<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Client::class);

        $search = trim((string) $request->query('q', ''));

        return Inertia::render('clients/index', [
            'clients' => Client::query()
                ->withCount('matters')
                ->when($search !== '', fn ($q) => $q->whereRaw('LOWER(full_name) LIKE ?', ['%'.mb_strtolower($search).'%']))
                ->orderBy('full_name')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Client $c) => [
                    'id' => $c->id, 'full_name' => $c->full_name, 'client_type' => $c->client_type, 'kra_pin' => $c->kra_pin,
                    'email' => $c->email, 'phone' => $c->phone, 'is_withholding_agent' => $c->is_withholding_agent,
                    'is_vat_exempt' => $c->is_vat_exempt, 'matters_count' => $c->matters_count,
                ]),
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Client::class);

        return Inertia::render('clients/create');
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        Gate::authorize('create', Client::class);

        $client = Client::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client added.')]);

        return to_route('clients.index');
    }

    public function edit(Client $client): Response
    {
        Gate::authorize('update', $client);

        return Inertia::render('clients/edit', ['client' => $client]);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $client->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return to_route('clients.index');
    }
}
