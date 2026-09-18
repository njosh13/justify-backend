import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ComputedSteps } from '@/components/billing/computed-steps';
import { FeeHeadPicker } from '@/components/billing/fee-head-picker';
import {
    emptyFeeInputs,
    FeeInputs,
    feeInputsToPayload,
    type FeeInputValues,
} from '@/components/billing/fee-inputs';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useFeePreview } from '@/hooks/use-fee-preview';
import { index } from '@/routes/calculator';
import type { AroVersionSummary, CatalogueItem } from '@/types/billing';

export default function Calculator({
    versions,
    currentVersionId,
    catalogue,
}: {
    versions: AroVersionSummary[];
    currentVersionId: string | null;
    catalogue: CatalogueItem[];
}) {
    const [versionId, setVersionId] = useState<string | null>(currentVersionId);
    const [item, setItem] = useState<CatalogueItem | null>(null);
    const [values, setValues] = useState<FeeInputValues>(
        emptyFeeInputs('party_party'),
    );

    const version = versions.find((v) => v.id === versionId) ?? null;

    const payload = useMemo(() => {
        if (!item || item.computation === 'pointer') {
            return null;
        }
        if (item.needs.basis && values.basis === '') {
            return null;
        }
        if (item.needs.quantity_required && values.quantity === '') {
            return null;
        }
        if (item.needs.scale && values.scale === '') {
            return null;
        }

        return {
            aro_version_id: versionId,
            item_code: item.code,
            ...feeInputsToPayload(values),
        };
    }, [item, values, versionId]);

    const { result, error, loading } = useFeePreview(payload);

    const chooseItem = (next: CatalogueItem | null) => {
        setItem(next);
        setValues(emptyFeeInputs(values.cost_basis));
    };

    return (
        <>
            <Head title="Fee calculator" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Fee calculator"
                        description="Runs the same engine a bill uses. Pick a head of the Order, enter what it needs, read every step."
                    />
                    <div className="flex items-center gap-2">
                        <Select
                            value={versionId ?? ''}
                            onValueChange={setVersionId}
                        >
                            <SelectTrigger className="w-64">
                                <SelectValue placeholder="ARO version" />
                            </SelectTrigger>
                            <SelectContent>
                                {versions.map((v) => (
                                    <SelectItem key={v.id} value={v.id}>
                                        {v.code} · {v.status}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {version && version.status !== 'published' && (
                            <Badge variant="destructive">not published</Badge>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-5">
                    <div className="space-y-6 lg:col-span-3">
                        <Card>
                            <CardHeader>
                                <CardTitle>1. Head of the Order</CardTitle>
                                <CardDescription>
                                    {catalogue.length} heads across twelve
                                    schedules. Inactive heads are greyed out.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FeeHeadPicker
                                    catalogue={catalogue}
                                    value={item?.id ?? null}
                                    onChange={chooseItem}
                                />
                            </CardContent>
                        </Card>

                        {item && item.computation !== 'pointer' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>2. Inputs</CardTitle>
                                    <CardDescription>
                                        {item.label} — {item.rule_reference}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <FeeInputs
                                        item={item}
                                        values={values}
                                        onChange={setValues}
                                        contentiousHint="Part B of the schedule uplifts the party-and-party figure by 50%."
                                    />
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="lg:col-span-2">
                        <Card className="sticky top-4">
                            <CardHeader>
                                <CardTitle>3. Result</CardTitle>
                                <CardDescription>
                                    Statutory figure with provenance you can
                                    read out at taxation.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {!item && (
                                    <p className="text-muted-foreground text-sm">
                                        Choose a head to begin.
                                    </p>
                                )}
                                {item?.computation === 'pointer' && (
                                    <Alert>
                                        <AlertTitle>
                                            Not a chargeable head
                                        </AlertTitle>
                                        <AlertDescription>
                                            {item.label}. Charge under{' '}
                                            {item.pointer_target}.
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {item &&
                                    item.computation !== 'pointer' &&
                                    payload === null && (
                                        <p className="text-muted-foreground text-sm">
                                            Fill in the required inputs to
                                            compute.
                                        </p>
                                    )}
                                {loading && !result && (
                                    <Skeleton className="h-24 w-full" />
                                )}
                                {error && (
                                    <Alert variant="destructive">
                                        <AlertTitle>
                                            The engine refused these inputs
                                        </AlertTitle>
                                        <AlertDescription>
                                            {error}
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {result && !error && (
                                    <ComputedSteps computed={result} />
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

Calculator.layout = {
    breadcrumbs: [{ title: 'Fee calculator', href: index() }],
};
