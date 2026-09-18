import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Pagination } from '@/components/billing/pagination';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatKes } from '@/lib/money';
import { create, index, show } from '@/routes/matters';
import type { MatterRow, Paginated } from '@/types/billing';

export default function MattersIndex({
    matters,
    filters,
}: {
    matters: Paginated<MatterRow>;
    filters: { q: string };
}) {
    return (
        <>
            <Head title="Matters" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Matters"
                        description={`${matters.total} on file`}
                    />
                    <div className="flex gap-2">
                        <Input
                            defaultValue={filters.q}
                            placeholder="Search by title…"
                            className="w-64"
                            onChange={(e) =>
                                router.get(
                                    index({ query: { q: e.target.value } }),
                                    {},
                                    { preserveState: true, replace: true },
                                )
                            }
                        />
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New matter
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-2 font-medium">
                                    Matter
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Client
                                </th>
                                <th className="px-4 py-2 font-medium">Forum</th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Value
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Unbilled
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {matters.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No matters yet.
                                    </td>
                                </tr>
                            )}
                            {matters.data.map((m) => (
                                <tr key={m.id} className="border-t">
                                    <td className="px-4 py-2">
                                        <Link
                                            href={show(m.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {m.title}
                                        </Link>
                                        {m.reference && (
                                            <div className="text-muted-foreground text-xs">
                                                {m.reference}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-2">{m.client}</td>
                                    <td className="px-4 py-2 text-xs">
                                        {m.court_level_label}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {formatKes(m.value_cents)}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {m.unbilled_count}
                                    </td>
                                    <td className="px-4 py-2">
                                        <Badge
                                            variant={
                                                m.status === 'open'
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {m.status}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination page={matters} />
            </div>
        </>
    );
}

MattersIndex.layout = { breadcrumbs: [{ title: 'Matters', href: index() }] };
