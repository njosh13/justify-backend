import { Head, router } from '@inertiajs/react';
import { scheduleName } from '@/components/billing/fee-head-picker';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatKes } from '@/lib/money';
import { index, show } from '@/routes/admin/aro';
import type { AroVersionSummary, CatalogueItem } from '@/types/billing';

type Interpretation = {
    id: string;
    code: string;
    rule_reference: string;
    question: string;
    decision: string;
    rationale: string | null;
    status: string;
};
type Props = {
    version: AroVersionSummary;
    items: CatalogueItem[];
    interpretations: Interpretation[];
    filters: { schedule: string | null; q: string | null };
};

export default function AroShow({
    version,
    items,
    interpretations,
    filters,
}: Props) {
    const refilter = (patch: Partial<Props['filters']>) =>
        router.get(
            show(version.id, { query: { ...filters, ...patch } }),
            {},
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={version.code} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title={version.code}
                        description={`${version.legal_notice} · ${items.length} heads shown`}
                    />
                    <div className="flex gap-2">
                        <select
                            value={filters.schedule ?? ''}
                            onChange={(e) =>
                                refilter({ schedule: e.target.value || null })
                            }
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="">All schedules</option>
                            {Array.from({ length: 12 }, (_, i) => i + 1).map(
                                (s) => (
                                    <option key={s} value={s}>
                                        {scheduleName(s)}
                                    </option>
                                ),
                            )}
                        </select>
                        <Input
                            defaultValue={filters.q ?? ''}
                            placeholder="Search…"
                            className="w-56"
                            onChange={(e) =>
                                refilter({ q: e.target.value || null })
                            }
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-3 py-2 font-medium">Code</th>
                                <th className="px-3 py-2 font-medium">Head</th>
                                <th className="px-3 py-2 font-medium">Rule</th>
                                <th className="px-3 py-2 font-medium">Shape</th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Figure
                                </th>
                                <th className="px-3 py-2 font-medium">Needs</th>
                                <th className="px-3 py-2 font-medium">
                                    Modifiers
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((i) => (
                                <tr
                                    key={i.id}
                                    className={`border-t ${i.is_active ? '' : 'opacity-50'}`}
                                >
                                    <td className="px-3 py-2 font-mono text-xs whitespace-nowrap">
                                        {i.code}
                                    </td>
                                    <td className="px-3 py-2">
                                        {i.label}
                                        {i.note && (
                                            <div className="text-muted-foreground text-xs">
                                                {i.note}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-xs whitespace-nowrap">
                                        {i.rule_reference}
                                    </td>
                                    <td className="px-3 py-2 text-xs">
                                        <Badge variant="outline">
                                            {i.computation}
                                        </Badge>{' '}
                                        {i.bound !== 'prescribed' && (
                                            <Badge variant="secondary">
                                                {i.bound}
                                            </Badge>
                                        )}{' '}
                                        {i.applies_cost_basis !==
                                            'non_contentious' && (
                                            <Badge variant="outline">
                                                {i.applies_cost_basis}
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-right text-xs tabular-nums">
                                        {i.included_amount_cents
                                            ? formatKes(i.included_amount_cents)
                                            : ''}
                                        {i.ceiling_cents
                                            ? ` ≤ ${formatKes(i.ceiling_cents)}`
                                            : ''}
                                    </td>
                                    <td className="px-3 py-2 text-xs">
                                        {Object.entries(i.needs)
                                            .filter(
                                                ([k, v]) =>
                                                    v === true &&
                                                    k !== 'quantity_required',
                                            )
                                            .map(([k]) => k)
                                            .join(', ')}
                                    </td>
                                    <td className="px-3 py-2 text-xs">
                                        {i.modifiers
                                            .map((m) => m.code)
                                            .join(', ')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Interpretations register</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {interpretations.map((r) => (
                            <div
                                key={r.id}
                                className="space-y-1 border-b pb-3 text-sm last:border-0"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="font-mono text-xs">
                                        {r.code}
                                    </span>
                                    <span className="font-medium">
                                        {r.rule_reference}
                                    </span>
                                    <Badge
                                        variant={
                                            r.status === 'adopted'
                                                ? 'default'
                                                : 'destructive'
                                        }
                                    >
                                        {r.status}
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    {r.question}
                                </p>
                                <p>{r.decision}</p>
                                {r.rationale && (
                                    <p className="text-muted-foreground text-xs">
                                        {r.rationale}
                                    </p>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AroShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'ARO catalogue', href: index() },
        { title: props.version.code, href: show(props.version.id) },
    ],
});
