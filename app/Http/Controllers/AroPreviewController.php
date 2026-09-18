<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Aro\Engine\Certificates;
use App\Domain\Aro\Engine\CostBasis;
use App\Domain\Aro\Engine\FeeRequest;
use App\Domain\Aro\Engine\Posture;
use App\Domain\Aro\Models\AroItem;
use App\Domain\Aro\Models\AroVersion;
use App\Domain\Aro\Services\FeeCalculator;
use App\Http\Requests\AroPreviewRequest;
use Brick\Money\Money;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Plan §10 `POST /aro/preview`: runs the same engine a bill uses, for live
 * previews and the calculator sandbox. Same inputs, same figure, same steps.
 */
final class AroPreviewController extends Controller
{
    public function __invoke(AroPreviewRequest $request, FeeCalculator $calculator): JsonResponse
    {
        $version = $request->filled('aro_version_id')
            ? AroVersion::query()->whereKey($request->input('aro_version_id'))->firstOrFail()
            : AroVersion::current();

        if ($version === null) {
            return response()->json(['message' => 'No ARO version is loaded.'], 422);
        }

        $money = fn (string $key) => $request->filled($key) ? Money::of((string) $request->input($key), 'KES') : null;
        $certificates = $request->input('certificates', []);

        try {
            $computed = $calculator->minimum(new FeeRequest(
                version: $version,
                itemCode: (string) $request->input('item_code'),
                basis: $money('basis'),
                quantity: $request->filled('quantity') ? (float) $request->input('quantity') : null,
                scale: $request->input('scale') ?: null,
                posture: $request->filled('posture') ? Posture::from($request->input('posture')) : null,
                modifierCodes: array_values(array_filter((array) $request->input('modifier_codes', []))),
                modifierAmounts: collect((array) $request->input('modifier_amounts', []))
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->map(fn ($v) => Money::of((string) $v, 'KES'))
                    ->all(),
                certificates: new Certificates(
                    twoAdvocates: (bool) ($certificates['two_advocates'] ?? false),
                    seniorCounsel: (bool) ($certificates['senior_counsel'] ?? false),
                ),
                costBasis: CostBasis::from($request->input('cost_basis', CostBasis::PartyParty->value)),
                agreedRate: $money('agreed_rate'),
                instructionFee: $money('instruction_fee'),
                contested: $request->boolean('contested'),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $item = AroItem::query()->where('aro_version_id', $version->id)->where('code', $request->input('item_code'))->first();

        return response()->json([
            ...$computed->toArray(),
            'version' => $version->only(['id', 'code', 'status']),
            'item' => $item?->only(['id', 'code', 'label', 'rule_reference', 'schedule']),
        ]);
    }
}
