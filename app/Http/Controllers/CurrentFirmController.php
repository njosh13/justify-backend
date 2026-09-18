<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Firm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CurrentFirmController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['firm_id' => ['required', Rule::exists(Firm::class, 'id')]]);

        $user = $request->user();
        abort_unless($user->firms()->whereKey($data['firm_id'])->exists(), 404);

        $user->forceFill(['current_firm_id' => $data['firm_id']])->save();

        return to_route('dashboard');
    }
}
