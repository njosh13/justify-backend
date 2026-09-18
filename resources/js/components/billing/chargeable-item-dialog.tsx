import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import ChargeableItemController from '@/actions/App/Http/Controllers/ChargeableItemController';
import { ComputedSteps } from '@/components/billing/computed-steps';
import { FeeHeadPicker } from '@/components/billing/fee-head-picker';
import {
    emptyFeeInputs,
    FeeInputs,
    feeInputsToPayload,
    type FeeInputValues,
} from '@/components/billing/fee-inputs';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { useFeePreview } from '@/hooks/use-fee-preview';
import { centsToInput, formatKes } from '@/lib/money';
import type {
    CatalogueItem,
    ChargeableItemRow,
    CostBasis,
    Posture,
} from '@/types/billing';

/** What the matter already knows; the dialog only collects per-line overrides. */
export type PricingContext = {
    basis_cents: number | null;
    scale: string | null;
    posture: Posture | null;
    certificates: { two_advocates: boolean; senior_counsel: boolean };
    contested: boolean;
    agreed_rate_cents: number | null;
    instruction_fee_cents: number | null;
    cost_basis: CostBasis;
    exempt: boolean;
    election: boolean;
    schedule: number | null;
};

type FormData = {
    kind: string;
    aro_item_id: string;
    description: string;
    occurred_on: string;
    quantity: string;
    unit: string;
    basis_override: string;
    scale_override: string;
    posture_override: string;
    modifier_codes: string[];
    modifier_amounts: Record<string, string>;
    entered: string;
    uplift_justification: string;
    is_billable: boolean;
};

export function ChargeableItemDialog({
    open,
    onOpenChange,
    matterId,
    catalogue,
    context,
    item,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    matterId: string;
    catalogue: CatalogueItem[];
    context: PricingContext;
    item?: ChargeableItemRow | null;
}) {
    const chargeable = useMemo(
        () => catalogue.filter((c) => c.computation !== 'pointer'),
        [catalogue],
    );
    const initialHead =
        catalogue.find((c) => c.id === item?.aro_item_id) ?? null;
    const [head, setHead] = useState<CatalogueItem | null>(initialHead);

    const form = useForm<FormData>({
        kind: item?.kind ?? 'fee',
        aro_item_id: item?.aro_item_id ?? '',
        description: item?.description ?? '',
        occurred_on: item?.occurred_on ?? new Date().toISOString().slice(0, 10),
        quantity: item?.quantity?.toString() ?? '',
        unit: item?.unit ?? '',
        basis_override: centsToInput(item?.basis_override_cents),
        scale_override: item?.scale_override ?? '',
        posture_override: item?.posture_override ?? '',
        modifier_codes: item?.modifier_codes ?? [],
        modifier_amounts: Object.fromEntries(
            Object.entries(item?.modifier_amounts ?? {}).map(([k, v]) => [
                k,
                centsToInput(v),
            ]),
        ),
        entered: centsToInput(item?.entered_cents),
        uplift_justification: item?.uplift_justification ?? '',
        is_billable: item?.is_billable ?? true,
    });
    const { data, setData, errors, processing } = form;
    const priced = data.kind === 'fee' || data.kind === 'time';

    const feeValues: FeeInputValues = {
        ...emptyFeeInputs(context.cost_basis),
        basis: data.basis_override,
        quantity: data.quantity,
        scale: data.scale_override,
        posture: data.posture_override,
        modifier_codes: data.modifier_codes,
        modifier_amounts: data.modifier_amounts,
    };

    const onFeeChange = (v: FeeInputValues) =>
        setData({
            ...data,
            basis_override: v.basis,
            quantity: v.quantity,
            scale_override: v.scale,
            posture_override: v.posture,
            modifier_codes: v.modifier_codes,
            modifier_amounts: v.modifier_amounts,
        });

    const effectiveBasis =
        data.basis_override !== ''
            ? data.basis_override
            : centsToInput(context.basis_cents);
    const effectiveScale = data.scale_override || context.scale || '';
    // Mirror the server: the matter's posture and certificates reach only heads that take them.
    const effectivePosture =
        data.posture_override ||
        (head?.needs.posture ? context.posture || '' : '');
    const effectiveCertificates = head?.needs.certificates
        ? context.certificates
        : { two_advocates: false, senior_counsel: false };

    const previewPayload = useMemo(() => {
        if (!priced || !head) {
            return null;
        }
        if (head.needs.basis && effectiveBasis === '') {
            return null;
        }
        if (head.needs.quantity_required && data.quantity === '') {
            return null;
        }
        if (head.needs.scale && effectiveScale === '') {
            return null;
        }

        return {
            item_code: head.code,
            ...feeInputsToPayload({
                ...feeValues,
                basis: effectiveBasis,
                scale: effectiveScale,
                posture: effectivePosture,
                certificates: effectiveCertificates,
                contested: context.contested,
                cost_basis: context.cost_basis,
            }),
            agreed_rate:
                context.agreed_rate_cents === null
                    ? null
                    : centsToInput(context.agreed_rate_cents),
            instruction_fee:
                context.instruction_fee_cents === null
                    ? null
                    : centsToInput(context.instruction_fee_cents),
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        priced,
        head,
        effectiveBasis,
        effectiveScale,
        effectivePosture,
        data.quantity,
        data.modifier_codes,
        data.modifier_amounts,
        context,
    ]);

    const { result, error, loading } = useFeePreview(previewPayload);

    const enteredCents = Math.round(Number(data.entered || 0) * 100);
    const comparison = result
        ? result.bound === 'maximum'
            ? enteredCents > result.amount_cents
                ? 'above-max'
                : 'ok'
            : enteredCents < result.amount_cents
              ? 'below'
              : enteredCents > result.amount_cents
                ? 'above'
                : 'ok'
        : null;

    const chooseHead = (next: CatalogueItem | null) => {
        setHead(next);
        setData({
            ...data,
            aro_item_id: next?.id ?? '',
            description:
                data.description === '' || data.description === head?.label
                    ? (next?.label ?? '')
                    : data.description,
            unit: next?.unit_label ?? '',
            modifier_codes: [],
            modifier_amounts: {},
            scale_override: '',
            posture_override: '',
        });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
        };
        if (item) {
            form.put(
                ChargeableItemController.update.url({
                    matter: matterId,
                    chargeableItem: item.id,
                }),
                options,
            );
        } else {
            form.post(ChargeableItemController.store.url(matterId), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {item ? 'Edit line' : 'Add chargeable work'}
                    </DialogTitle>
                    <DialogDescription>
                        Fee and time lines are priced by the Order as you type.
                        Disbursements and recharges take the amount as entered.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="ci-kind">Kind</Label>
                            <select
                                id="ci-kind"
                                value={data.kind}
                                onChange={(e) =>
                                    setData('kind', e.target.value)
                                }
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            >
                                <option value="fee">Professional fee</option>
                                <option value="time">Time</option>
                                <option value="disbursement">
                                    Disbursement (paid as agent)
                                </option>
                                <option value="recharge">
                                    Recharge (firm expense)
                                </option>
                            </select>
                            <InputError message={errors.kind} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="ci-date">Date of work</Label>
                            <Input
                                id="ci-date"
                                type="date"
                                value={data.occurred_on}
                                onChange={(e) =>
                                    setData('occurred_on', e.target.value)
                                }
                                required
                            />
                            <InputError message={errors.occurred_on} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="ci-entered">
                                Amount to charge (KES)
                            </Label>
                            <Input
                                id="ci-entered"
                                type="number"
                                min={0}
                                step="0.01"
                                value={data.entered}
                                onChange={(e) =>
                                    setData('entered', e.target.value)
                                }
                                required
                            />
                            <InputError message={errors.entered} />
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="ci-desc">
                            Particulars (printed on the bill)
                        </Label>
                        <Input
                            id="ci-desc"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            required
                        />
                        <InputError message={errors.description} />
                    </div>

                    {priced && (
                        <div className="grid gap-5 lg:grid-cols-5">
                            <div className="space-y-4 lg:col-span-3">
                                <div className="space-y-1.5">
                                    <Label>Head of the Order</Label>
                                    <FeeHeadPicker
                                        catalogue={chargeable}
                                        value={head?.id ?? null}
                                        onChange={chooseHead}
                                        preferredSchedule={context.schedule}
                                    />
                                    <InputError message={errors.aro_item_id} />
                                </div>
                                {head && (
                                    <div className="space-y-2 rounded-md border p-3">
                                        <p className="text-muted-foreground text-xs">
                                            Inherited from the matter: basis{' '}
                                            {formatKes(context.basis_cents)}
                                            {context.scale
                                                ? ` · ${context.scale} scale`
                                                : ''}
                                            {context.posture
                                                ? ` · ${context.posture.replace(/_/g, ' ')}`
                                                : ''}
                                            . Leave a field blank to keep the
                                            matter's value.
                                        </p>
                                        <FeeInputs
                                            item={head}
                                            values={feeValues}
                                            onChange={onFeeChange}
                                            hide={[
                                                'certificates',
                                                'contested',
                                                'cost_basis',
                                                'agreed_rate',
                                                'instruction_fee',
                                            ]}
                                        />
                                        <InputError
                                            message={
                                                (
                                                    errors as Record<
                                                        string,
                                                        string | undefined
                                                    >
                                                ).pricing
                                            }
                                        />
                                    </div>
                                )}
                            </div>
                            <div className="lg:col-span-2">
                                <div className="bg-muted/30 space-y-3 rounded-md border p-3">
                                    <div className="text-muted-foreground text-xs uppercase">
                                        Statutory figure
                                    </div>
                                    {!head && (
                                        <p className="text-muted-foreground text-sm">
                                            Pick a head.
                                        </p>
                                    )}
                                    {head && previewPayload === null && (
                                        <p className="text-muted-foreground text-sm">
                                            Complete the inputs.
                                        </p>
                                    )}
                                    {loading && !result && (
                                        <Skeleton className="h-16 w-full" />
                                    )}
                                    {error && (
                                        <Alert variant="destructive">
                                            <AlertTitle>
                                                Cannot price
                                            </AlertTitle>
                                            <AlertDescription>
                                                {error}
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                    {result && !error && (
                                        <>
                                            <ComputedSteps
                                                computed={result}
                                                compact
                                            />
                                            {comparison === 'below' &&
                                                !context.exempt &&
                                                !context.election && (
                                                    <Alert variant="destructive">
                                                        <AlertTitle>
                                                            Below scale
                                                        </AlertTitle>
                                                        <AlertDescription>
                                                            Para 3: the Order's
                                                            minimum is{' '}
                                                            {formatKes(
                                                                result.amount_cents,
                                                            )}
                                                            . Raise the amount,
                                                            or record an
                                                            exemption / para 22
                                                            election on the
                                                            matter.
                                                        </AlertDescription>
                                                    </Alert>
                                                )}
                                            {comparison === 'below' &&
                                                (context.exempt ||
                                                    context.election) && (
                                                    <Alert>
                                                        <AlertTitle>
                                                            Below scale —
                                                            allowed
                                                        </AlertTitle>
                                                        <AlertDescription>
                                                            {context.exempt
                                                                ? 'This matter is exempt from scale minimums.'
                                                                : 'A para 22 election has been communicated in writing.'}
                                                        </AlertDescription>
                                                    </Alert>
                                                )}
                                            {comparison === 'above-max' && (
                                                <Alert variant="destructive">
                                                    <AlertTitle>
                                                        Above the ceiling
                                                    </AlertTitle>
                                                    <AlertDescription>
                                                        This head is "not
                                                        exceeding"{' '}
                                                        {formatKes(
                                                            result.amount_cents,
                                                        )}
                                                        .
                                                    </AlertDescription>
                                                </Alert>
                                            )}
                                            {comparison === 'above' && (
                                                <div className="grid gap-1.5">
                                                    <Label htmlFor="ci-just">
                                                        Why more than{' '}
                                                        {formatKes(
                                                            result.amount_cents,
                                                        )}
                                                        ? (required; cited on
                                                        taxation)
                                                    </Label>
                                                    <Input
                                                        id="ci-just"
                                                        value={
                                                            data.uplift_justification
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'uplift_justification',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="e.g. para 5 special fee: unusual complexity, three consents"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.uplift_justification
                                                        }
                                                    />
                                                </div>
                                            )}
                                            {comparison === 'ok' && (
                                                <p className="text-xs text-emerald-700 dark:text-emerald-400">
                                                    Matches the Order.
                                                </p>
                                            )}
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setData(
                                                        'entered',
                                                        centsToInput(
                                                            result.amount_cents,
                                                        ),
                                                    )
                                                }
                                            >
                                                Use statutory figure
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {errors.uplift_justification && comparison !== 'above' && (
                        <InputError message={errors.uplift_justification} />
                    )}

                    <div className="flex items-center justify-between gap-2">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.is_billable}
                                onChange={(e) =>
                                    setData('is_billable', e.target.checked)
                                }
                            />{' '}
                            Billable
                        </label>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => onOpenChange(false)}
                            >
                                Cancel
                            </Button>
                            <Button disabled={processing || (priced && !head)}>
                                {item ? 'Save line' : 'Add line'}
                            </Button>
                        </div>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
