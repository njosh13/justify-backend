import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatKes, labelFor } from '@/lib/money';
import { dashboard } from '@/routes';
import { show as billShow } from '@/routes/bills';
import { index as calculatorIndex } from '@/routes/calculator';
import { index as mattersIndex } from '@/routes/matters';
import type { BillRow } from '@/types/billing';

type Stats = {
    clients: number;
    open_matters: number;
    unbilled_cents: number;
    unbilled_items: number;
    shortfall_matters: number;
    bills: {
        status: string;
        count: number;
        total_cents: number;
        outstanding_cents: number;
    }[];
};

export default function Dashboard({
    stats,
    recentBills,
}: {
    stats: Stats;
    recentBills: BillRow[];
}) {
    const outstanding = stats.bills
        .filter((b) =>
            ['issued', 'delivered', 'disputed', 'partially_paid'].includes(
                b.status,
            ),
        )
        .reduce((s, b) => s + b.outstanding_cents, 0);

    return (
        <>
            <Head title="Dashboard" />
            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    title="Overview"
                    description="Work in progress, statutory shortfall and receivables for this firm."
                />

                <div className="grid gap-4 md:grid-cols-4">
                    <Stat
                        label="Unbilled work"
                        value={formatKes(stats.unbilled_cents)}
                        hint={`${stats.unbilled_items} line${stats.unbilled_items === 1 ? '' : 's'} across open matters`}
                    />
                    <Stat
                        label="Below scale"
                        value={String(stats.shortfall_matters)}
                        hint="matters with a para 3 shortfall"
                        tone={stats.shortfall_matters > 0 ? 'warn' : undefined}
                    />
                    <Stat
                        label="Receivable"
                        value={formatKes(outstanding)}
                        hint="issued bills not yet paid in full"
                    />
                    <Stat
                        label="Open matters"
                        value={String(stats.open_matters)}
                        hint={`${stats.clients} client${stats.clients === 1 ? '' : 's'}`}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Recent bills</CardTitle>
                            <CardDescription>
                                Drafts, issued and paid.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recentBills.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No bills yet. Open a{' '}
                                    <Link
                                        href={mattersIndex()}
                                        className="underline"
                                    >
                                        matter
                                    </Link>
                                    , add chargeable work, then assemble a bill.
                                </p>
                            ) : (
                                <table className="w-full text-sm">
                                    <tbody>
                                        {recentBills.map((b) => (
                                            <tr
                                                key={b.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="py-2 pr-2">
                                                    <Link
                                                        href={billShow(b.id)}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {b.number ?? 'Draft'}
                                                    </Link>
                                                    <div className="text-muted-foreground text-xs">
                                                        {b.client} · {b.matter}
                                                    </div>
                                                </td>
                                                <td className="py-2 pr-2">
                                                    <Badge variant="outline">
                                                        {labelFor(b.status)}
                                                    </Badge>
                                                </td>
                                                <td className="py-2 text-right tabular-nums">
                                                    {formatKes(b.total_cents)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Test the engine</CardTitle>
                            <CardDescription>
                                Compute any head of the Order without a matter.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <p>
                                The{' '}
                                <Link
                                    href={calculatorIndex()}
                                    className="underline"
                                >
                                    fee calculator
                                </Link>{' '}
                                runs the same code a bill uses and prints every
                                step with its rule reference.
                            </p>
                            <ul className="text-muted-foreground list-disc space-y-1 pl-4 text-xs">
                                {stats.bills
                                    .filter((b) => b.count > 0)
                                    .map((b) => (
                                        <li key={b.status}>
                                            {b.count}{' '}
                                            {labelFor(b.status).toLowerCase()} ·{' '}
                                            {formatKes(b.total_cents)}
                                        </li>
                                    ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    hint,
    tone,
}: {
    label: string;
    value: string;
    hint: string;
    tone?: 'warn';
}) {
    return (
        <Card className={tone === 'warn' ? 'border-amber-400/60' : undefined}>
            <CardContent className="space-y-1">
                <div className="text-muted-foreground text-xs tracking-wide uppercase">
                    {label}
                </div>
                <div className="text-2xl font-semibold tabular-nums">
                    {value}
                </div>
                <div className="text-muted-foreground text-xs">{hint}</div>
            </CardContent>
        </Card>
    );
}

Dashboard.layout = { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] };
