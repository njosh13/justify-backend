import { Head, Link, router } from '@inertiajs/react';
import { Pagination } from '@/components/billing/pagination';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { formatKes, labelFor } from '@/lib/money';
import { index, show } from '@/routes/bills';
import type { BillRow, Paginated } from '@/types/billing';

const statuses = [
    '',
    'draft',
    'issued',
    'delivered',
    'disputed',
    'partially_paid',
    'paid',
    'voided',
];

export default function BillsIndex({
    bills,
    filters,
}: {
    bills: Paginated<BillRow>;
    filters: { status: string };
}) {
    return (
        <>
            <Head title="Bills" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Bills"
                        description={`${bills.total} in total`}
                    />
                    <select
                        value={filters.status}
                        onChange={(e) =>
                            router.get(
                                index({ query: { status: e.target.value } }),
                                {},
                                { preserveState: true, replace: true },
                            )
                        }
                        className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                    >
                        {statuses.map((s) => (
                            <option key={s} value={s}>
                                {s === '' ? 'All statuses' : labelFor(s)}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-2 font-medium">
                                    Number
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Client / matter
                                </th>
                                <th className="px-4 py-2 font-medium">Type</th>
                                <th className="px-4 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Total
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Outstanding
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Issued
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {bills.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No bills.
                                    </td>
                                </tr>
                            )}
                            {bills.data.map((b) => (
                                <tr key={b.id} className="border-t">
                                    <td className="px-4 py-2">
                                        <Link
                                            href={show(b.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {b.number ?? 'Draft'}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-2">
                                        {b.client}
                                        <div className="text-muted-foreground text-xs">
                                            {b.matter}
                                        </div>
                                    </td>
                                    <td className="px-4 py-2 text-xs">
                                        {labelFor(b.type)} ·{' '}
                                        {labelFor(b.cost_basis)}
                                    </td>
                                    <td className="px-4 py-2">
                                        <Badge variant="outline">
                                            {labelFor(b.status)}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {formatKes(b.total_cents)}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {formatKes(
                                            b.total_cents - (b.paid_cents ?? 0),
                                        )}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-2 text-xs">
                                        {b.issued_at ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination page={bills} />
            </div>
        </>
    );
}

BillsIndex.layout = { breadcrumbs: [{ title: 'Bills', href: index() }] };
