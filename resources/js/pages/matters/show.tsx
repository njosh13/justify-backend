import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { BillDraftDialog } from '@/components/billing/bill-draft-dialog';
import {
    ChargeableItemDialog,
    type PricingContext,
} from '@/components/billing/chargeable-item-dialog';
import { ClassificationCard } from '@/components/billing/classification-card';
import { FeeAgreementsCard } from '@/components/billing/fee-agreements-card';
import { ItemsTable } from '@/components/billing/items-table';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatKes, labelFor } from '@/lib/money';
import { show as billShow } from '@/routes/bills';
import { edit, index, show } from '@/routes/matters';
import type { Auth } from '@/types';
import type {
    AroVersionSummary,
    BillRow,
    CatalogueItem,
    ChargeableItemRow,
    Classification,
    CourtLevelOption,
    FeeAgreementRow,
    Shortfall,
} from '@/types/billing';
import { usePage } from '@inertiajs/react';

type Matter = {
    id: string;
    title: string;
    reference: string | null;
    court_level: string;
    court_level_label: string;
    schedule: number | null;
    cause_number: string | null;
    description: string | null;
    value_cents: number | null;
    status: string;
    opened_on: string | null;
    client: {
        id: string;
        full_name: string;
        kra_pin: string | null;
        is_withholding_agent: boolean;
        is_vat_exempt: boolean;
    };
};

type Props = {
    matter: Matter;
    classification: Classification | null;
    feeAgreements: FeeAgreementRow[];
    items: ChargeableItemRow[];
    shortfall: Shortfall;
    bills: BillRow[];
    catalogue: CatalogueItem[];
    aroVersion: AroVersionSummary | null;
    courtLevels: CourtLevelOption[];
    can: { bill: boolean; update: boolean };
};

export default function MatterShow({
    matter,
    classification,
    feeAgreements,
    items,
    shortfall,
    bills,
    catalogue,
    aroVersion,
    can,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<ChargeableItemRow | null>(null);
    const [drafting, setDrafting] = useState(false);
    const [selected, setSelected] = useState<Set<string>>(new Set());

    const hourly = feeAgreements.find((a) => a.type === 'hourly');
    const instruction = items.find(
        (i) =>
            i.aro_item &&
            catalogue.find((c) => c.id === i.aro_item_id)?.is_instruction_fee,
    );

    const context: PricingContext = useMemo(
        () => ({
            basis_cents: classification?.basis_cents ?? matter.value_cents,
            scale: classification?.scale ?? null,
            posture: classification?.posture ?? null,
            certificates: {
                two_advocates: !!classification?.certificates?.two_advocates,
                senior_counsel: !!classification?.certificates?.senior_counsel,
            },
            contested: classification?.contested ?? false,
            agreed_rate_cents: hourly?.hourly_rate_cents ?? null,
            instruction_fee_cents: instruction?.entered_cents ?? null,
            cost_basis: auth.firm?.default_cost_basis ?? 'advocate_client',
            exempt: shortfall.exempt,
            election: shortfall.election,
            schedule: matter.schedule,
        }),
        [classification, matter, hourly, instruction, auth.firm, shortfall],
    );

    const unbilled = items.filter((i) => i.bill_id === null && i.is_billable);
    const toggle = (id: string, on: boolean) =>
        setSelected((s) => {
            const n = new Set(s);
            if (on) {
                n.add(id);
            } else {
                n.delete(id);
            }
            return n;
        });
    const selectAll = () => setSelected(new Set(unbilled.map((i) => i.id)));

    return (
        <>
            <Head title={matter.title} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Heading
                            title={matter.title}
                            description={`${matter.client.full_name}${matter.reference ? ` · ${matter.reference}` : ''}${matter.cause_number ? ` · ${matter.cause_number}` : ''}`}
                        />
                        <div className="-mt-6 flex flex-wrap gap-2 text-xs">
                            <Badge variant="outline">
                                {matter.court_level_label}
                                {matter.schedule
                                    ? ` · Sch ${matter.schedule}`
                                    : ''}
                            </Badge>
                            <Badge
                                variant={
                                    matter.status === 'open'
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {matter.status}
                            </Badge>
                            {matter.client.is_withholding_agent && (
                                <Badge variant="secondary">WHT agent</Badge>
                            )}
                            {matter.client.is_vat_exempt && (
                                <Badge variant="outline">VAT exempt</Badge>
                            )}
                            {aroVersion && (
                                <Badge
                                    variant={
                                        aroVersion.status === 'published'
                                            ? 'outline'
                                            : 'destructive'
                                    }
                                >
                                    {aroVersion.code} · {aroVersion.status}
                                </Badge>
                            )}
                        </div>
                    </div>
                    {can.update && (
                        <Button asChild variant="outline">
                            <Link href={edit(matter.id)}>Edit matter</Link>
                        </Button>
                    )}
                </div>

                {shortfall.blocking && (
                    <Alert variant="destructive">
                        <AlertTitle>
                            Below the statutory minimum by{' '}
                            {formatKes(shortfall.total_cents)}
                        </AlertTitle>
                        <AlertDescription>
                            Para 3: no advocate may agree to less than the Order
                            provides. A bill cannot be issued until the lines
                            are raised, the matter is marked exempt, or a para
                            22 election is communicated in writing.
                        </AlertDescription>
                    </Alert>
                )}
                {shortfall.total_cents > 0 && !shortfall.blocking && (
                    <Alert>
                        <AlertTitle>
                            Below scale by {formatKes(shortfall.total_cents)} —
                            permitted
                        </AlertTitle>
                        <AlertDescription>
                            {shortfall.exempt
                                ? 'This matter is exempt from scale minimums.'
                                : 'A para 22 election to Schedule 5 has been communicated in writing.'}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <ClassificationCard
                        matterId={matter.id}
                        classification={classification}
                        catalogue={catalogue}
                        schedule={matter.schedule}
                        canEdit={can.bill}
                    />
                    <FeeAgreementsCard
                        matterId={matter.id}
                        agreements={feeAgreements}
                        canEdit={can.bill}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <CardTitle>Chargeable work</CardTitle>
                                <CardDescription>
                                    {items.length} line
                                    {items.length === 1 ? '' : 's'} · unbilled{' '}
                                    {formatKes(
                                        unbilled.reduce(
                                            (s, i) => s + i.entered_cents,
                                            0,
                                        ),
                                    )}
                                </CardDescription>
                            </div>
                            {can.bill && (
                                <div className="flex flex-wrap gap-2">
                                    {unbilled.length > 0 &&
                                        selected.size < unbilled.length && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={selectAll}
                                            >
                                                Select all unbilled
                                            </Button>
                                        )}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={selected.size === 0}
                                        onClick={() => setDrafting(true)}
                                    >
                                        Assemble bill ({selected.size})
                                    </Button>
                                    <Button
                                        size="sm"
                                        onClick={() => setAdding(true)}
                                    >
                                        <Plus /> Add line
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="px-0">
                        <ItemsTable
                            matterId={matter.id}
                            items={items}
                            canEdit={can.bill}
                            selected={selected}
                            onToggle={toggle}
                            onEdit={setEditing}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Bills</CardTitle>
                        <CardDescription>
                            Drafts can be discarded; issued bills are locked.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {bills.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                None yet.
                            </p>
                        ) : (
                            <table className="w-full text-sm">
                                <tbody>
                                    {bills.map((b) => (
                                        <tr
                                            key={b.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2">
                                                <Link
                                                    href={billShow(b.id)}
                                                    className="font-medium hover:underline"
                                                >
                                                    {b.number ?? 'Draft'}
                                                </Link>{' '}
                                                <span className="text-muted-foreground text-xs">
                                                    {labelFor(b.type)} ·{' '}
                                                    {labelFor(b.cost_basis)}
                                                </span>
                                            </td>
                                            <td className="py-2">
                                                <Badge variant="outline">
                                                    {labelFor(b.status)}
                                                </Badge>
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {formatKes(b.total_cents)}
                                            </td>
                                            <td className="text-muted-foreground py-2 text-right text-xs">
                                                {b.created_at}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>
            </div>

            {can.bill && adding && (
                <ChargeableItemDialog
                    key="new"
                    open={adding}
                    onOpenChange={setAdding}
                    matterId={matter.id}
                    catalogue={catalogue}
                    context={context}
                />
            )}
            {can.bill && editing && (
                <ChargeableItemDialog
                    key={editing.id}
                    open={!!editing}
                    onOpenChange={(o) => !o && setEditing(null)}
                    matterId={matter.id}
                    catalogue={catalogue}
                    context={context}
                    item={editing}
                />
            )}
            {can.bill && (
                <BillDraftDialog
                    open={drafting}
                    onOpenChange={setDrafting}
                    matterId={matter.id}
                    itemIds={Array.from(selected)}
                    defaultBasis={
                        auth.firm?.default_cost_basis ?? 'advocate_client'
                    }
                    contentious={matter.schedule !== null}
                />
            )}
        </>
    );
}

MatterShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Matters', href: index() },
        { title: props.matter.title, href: show(props.matter.id) },
    ],
});
