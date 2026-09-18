import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Pagination } from '@/components/billing/pagination';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index } from '@/routes/clients';
import type { ClientRow, Paginated } from '@/types/billing';

export default function ClientsIndex({
    clients,
    filters,
}: {
    clients: Paginated<ClientRow>;
    filters: { q: string };
}) {
    return (
        <>
            <Head title="Clients" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Clients"
                        description={`${clients.total} on file`}
                    />
                    <div className="flex gap-2">
                        <Input
                            defaultValue={filters.q}
                            placeholder="Search by name…"
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
                                <Plus /> New client
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-2 font-medium">Name</th>
                                <th className="px-4 py-2 font-medium">Type</th>
                                <th className="px-4 py-2 font-medium">
                                    KRA PIN
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Contact
                                </th>
                                <th className="px-4 py-2 font-medium">Tax</th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Matters
                                </th>
                                <th className="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {clients.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No clients yet.
                                    </td>
                                </tr>
                            )}
                            {clients.data.map((c) => (
                                <tr key={c.id} className="border-t">
                                    <td className="px-4 py-2 font-medium">
                                        {c.full_name}
                                    </td>
                                    <td className="px-4 py-2 capitalize">
                                        {c.client_type}
                                    </td>
                                    <td className="px-4 py-2 font-mono text-xs">
                                        {c.kra_pin ?? '—'}
                                    </td>
                                    <td className="px-4 py-2 text-xs">
                                        {c.email ?? ''}
                                        {c.email && c.phone ? ' · ' : ''}
                                        {c.phone ?? ''}
                                    </td>
                                    <td className="space-x-1 px-4 py-2">
                                        {c.is_withholding_agent && (
                                            <Badge variant="secondary">
                                                WHT agent
                                            </Badge>
                                        )}
                                        {c.is_vat_exempt && (
                                            <Badge variant="outline">
                                                VAT exempt
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {c.matters_count ?? 0}
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <Link href={edit(c.id)}>Edit</Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination page={clients} />
            </div>
        </>
    );
}

ClientsIndex.layout = { breadcrumbs: [{ title: 'Clients', href: index() }] };
