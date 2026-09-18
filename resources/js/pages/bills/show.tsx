import { Form, Head, Link } from '@inertiajs/react';
import { Download, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import BillController from '@/actions/App/Http/Controllers/BillController';
import BillInterestClaimController from '@/actions/App/Http/Controllers/BillInterestClaimController';
import BillPaymentController from '@/actions/App/Http/Controllers/BillPaymentController';
import DeliveredBillController from '@/actions/App/Http/Controllers/DeliveredBillController';
import IssuedBillController from '@/actions/App/Http/Controllers/IssuedBillController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatAmount, formatKes, labelFor } from '@/lib/money';
import { index, pdf, preview, show } from '@/routes/bills';
import { show as matterShow } from '@/routes/matters';
import type { BillLine } from '@/types/billing';

type Bill = {
    id: string;
    number: string | null;
    type: string;
    type_label: string;
    status: string;
    cost_basis: string;
    fees_cents: number;
    recharges_cents: number;
    disbursements_cents: number;
    vat_cents: number;
    wht_expected_cents: number;
    total_cents: number;
    paid_cents: number;
    outstanding_cents: number;
    issued_at: string | null;
    issued_by: string | null;
    delivered_at: string | null;
    delivery_method: string | null;
    deemed_agreed_at: string | null;
    interest_claimed_at: string | null;
    paid_in_full_at: string | null;
    locked_at: string | null;
    pdf_path: string | null;
    interest_accrued_cents: number;
    aro_version: { code: string; legal_notice: string; status: string };
    client: {
        id: string;
        full_name: string;
        kra_pin: string | null;
        is_withholding_agent: boolean;
        is_vat_exempt: boolean;
    };
    matter: {
        id: string;
        title: string;
        reference: string | null;
        cause_number: string | null;
    };
    created_at: string | null;
};
type Event = {
    id: string;
    type: string;
    user: string | null;
    payload: Record<string, unknown> | null;
    created_at: string;
};
type Payment = {
    id: string;
    amount_cents: number;
    method: string;
    reference: string | null;
    received_at: string;
    allocated_to: string;
    wht_certificate_reference: string | null;
};
type Props = {
    bill: Bill;
    lines: BillLine[];
    events: Event[];
    payments: Payment[];
    can: {
        issue: boolean;
        delete: boolean;
        deliver: boolean;
        claimInterest: boolean;
        recordPayment: boolean;
    };
};

export default function BillShow({
    bill,
    lines,
    events,
    payments,
    can,
}: Props) {
    const [paying, setPaying] = useState(false);
    const [delivering, setDelivering] = useState(false);
    const fees = lines.filter((l) => l.section === 'fees');
    const disbursements = lines.filter((l) => l.section === 'disbursements');
    const taxation = lines.filter((l) => l.section === 'taxation_attendance');

    return (
        <>
            <Head title={bill.number ?? 'Draft bill'} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Heading
                            title={bill.number ?? 'Draft bill'}
                            description={`${bill.type_label} · ${labelFor(bill.cost_basis)} · ${bill.client.full_name}`}
                        />
                        <div className="-mt-6 flex flex-wrap gap-2 text-xs">
                            <Badge
                                variant={
                                    bill.status === 'draft'
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {labelFor(bill.status)}
                            </Badge>
                            {bill.locked_at && (
                                <Badge variant="outline">
                                    locked {bill.locked_at}
                                </Badge>
                            )}
                            <Badge
                                variant={
                                    bill.aro_version.status === 'published'
                                        ? 'outline'
                                        : 'destructive'
                                }
                            >
                                {bill.aro_version.code} ·{' '}
                                {bill.aro_version.status}
                            </Badge>
                            <Link
                                href={matterShow(bill.matter.id)}
                                className="underline"
                            >
                                {bill.matter.title}
                            </Link>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <a
                                href={preview.url(bill.id)}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink /> Preview
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <a href={pdf.url(bill.id)}>
                                <Download /> PDF
                            </a>
                        </Button>
                        {can.issue && (
                            <Form {...IssuedBillController.store.form(bill.id)}>
                                {({ processing, errors }) => (
                                    <div className="space-y-1">
                                        <Button disabled={processing}>
                                            Issue &amp; lock
                                        </Button>
                                        <InputError message={errors.issue} />
                                    </div>
                                )}
                            </Form>
                        )}
                        {can.delete && (
                            <Link
                                href={BillController.destroy(bill.id)}
                                method="delete"
                                as="button"
                                className="text-muted-foreground px-2 text-xs hover:underline"
                            >
                                Discard draft
                            </Link>
                        )}
                        {can.deliver && bill.delivered_at === null && (
                            <Button
                                variant="outline"
                                onClick={() => setDelivering(true)}
                            >
                                Record delivery
                            </Button>
                        )}
                        {can.claimInterest && (
                            <Link
                                href={BillInterestClaimController.store(
                                    bill.id,
                                )}
                                method="post"
                                as="button"
                                className="text-xs underline"
                            >
                                Claim para 7 interest
                            </Link>
                        )}
                        {can.recordPayment && bill.outstanding_cents > 0 && (
                            <Button
                                variant="outline"
                                onClick={() => setPaying(true)}
                            >
                                Record payment
                            </Button>
                        )}
                    </div>
                </div>

                {bill.status === 'draft' &&
                    bill.aro_version.status !== 'published' && (
                        <Alert variant="destructive">
                            <AlertTitle>ARO version not published</AlertTitle>
                            <AlertDescription>
                                A bill may only be issued on a published
                                version. Review and publish{' '}
                                {bill.aro_version.code} under ARO catalogue.
                            </AlertDescription>
                        </Alert>
                    )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Lines</CardTitle>
                            <CardDescription>
                                Para 69 order: date, serial number, particulars,
                                charge claimed, taxed off. Provenance under each
                                fee line.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            Date
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            No.
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            Particulars
                                        </th>
                                        <th className="px-2 py-2 text-center font-medium">
                                            Tax
                                        </th>
                                        <th className="px-2 py-2 text-right font-medium">
                                            Claimed
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            Taxed off
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <Section
                                        label="Professional charges"
                                        rows={fees}
                                    />
                                    {disbursements.length > 0 && (
                                        <Section
                                            label="Disbursements (para 69(2))"
                                            rows={disbursements}
                                        />
                                    )}
                                    {taxation.length > 0 && (
                                        <Section
                                            label=""
                                            rows={taxation}
                                            blank
                                        />
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Totals</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="space-y-1 text-sm">
                                    <Total
                                        label="Professional fees"
                                        cents={bill.fees_cents}
                                    />
                                    {bill.recharges_cents > 0 && (
                                        <Total
                                            label="Recharges"
                                            cents={bill.recharges_cents}
                                        />
                                    )}
                                    <Total
                                        label="VAT 16%"
                                        cents={bill.vat_cents}
                                    />
                                    <Total
                                        label="Disbursements"
                                        cents={bill.disbursements_cents}
                                    />
                                    <div className="flex justify-between border-t pt-2 text-base font-semibold">
                                        <dt>Total</dt>
                                        <dd className="tabular-nums">
                                            {formatKes(bill.total_cents)}
                                        </dd>
                                    </div>
                                    {bill.client.is_withholding_agent && (
                                        <Total
                                            label="WHT the client will withhold (5%)"
                                            cents={bill.wht_expected_cents}
                                            muted
                                        />
                                    )}
                                    <Total
                                        label="Paid"
                                        cents={bill.paid_cents}
                                        muted
                                    />
                                    <div className="flex justify-between font-medium">
                                        <dt>Outstanding</dt>
                                        <dd className="tabular-nums">
                                            {formatKes(bill.outstanding_cents)}
                                        </dd>
                                    </div>
                                    {bill.interest_claimed_at && (
                                        <Total
                                            label="Para 7 interest accrued to date (14% p.a.)"
                                            cents={bill.interest_accrued_cents}
                                            muted
                                        />
                                    )}
                                </dl>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Lifecycle</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="space-y-1 text-sm">
                                    <Row label="Created">{bill.created_at}</Row>
                                    <Row label="Issued">
                                        {bill.issued_at
                                            ? `${bill.issued_at} by ${bill.issued_by ?? ''}`
                                            : '—'}
                                    </Row>
                                    <Row label="Delivered">
                                        {bill.delivered_at
                                            ? `${bill.delivered_at} (${bill.delivery_method})`
                                            : '—'}
                                    </Row>
                                    <Row label="Deemed agreed (para 6)">
                                        {bill.deemed_agreed_at ?? '—'}
                                    </Row>
                                    <Row label="Interest claimed (para 7)">
                                        {bill.interest_claimed_at ?? '—'}
                                    </Row>
                                    <Row label="Paid in full">
                                        {bill.paid_in_full_at ?? '—'}
                                    </Row>
                                </dl>
                                <ul className="text-muted-foreground mt-4 space-y-1 text-xs">
                                    {events.map((e) => (
                                        <li key={e.id}>
                                            {e.created_at} · {labelFor(e.type)}
                                            {e.user ? ` · ${e.user}` : ''}
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>

                        {payments.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Payments</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="space-y-1 text-sm">
                                        {payments.map((p) => (
                                            <li
                                                key={p.id}
                                                className="flex justify-between"
                                            >
                                                <span>
                                                    {p.received_at} · {p.method}
                                                    {p.reference
                                                        ? ` · ${p.reference}`
                                                        : ''}
                                                </span>
                                                <span className="tabular-nums">
                                                    {formatKes(p.amount_cents)}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            <Dialog open={delivering} onOpenChange={setDelivering}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Record delivery</DialogTitle>
                        <DialogDescription>
                            Starts the one-month clocks for deemed agreement
                            (para 6) and interest (para 7).
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...DeliveredBillController.store.form(bill.id)}
                        className="space-y-4"
                        onSuccess={() => setDelivering(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="delivery_method">
                                        Method
                                    </Label>
                                    <select
                                        id="delivery_method"
                                        name="delivery_method"
                                        defaultValue="email"
                                        className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                    >
                                        <option value="email">Email</option>
                                        <option value="hand">By hand</option>
                                        <option value="post">Post</option>
                                        <option value="courier">Courier</option>
                                        <option value="portal">
                                            Client portal
                                        </option>
                                    </select>
                                    <InputError
                                        message={errors.delivery_method}
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="delivered_at">
                                        Delivered on
                                    </Label>
                                    <Input
                                        id="delivered_at"
                                        name="delivered_at"
                                        type="datetime-local"
                                    />
                                    <InputError message={errors.delivered_at} />
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setDelivering(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button disabled={processing}>
                                        Record
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog open={paying} onOpenChange={setPaying}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Record payment</DialogTitle>
                        <DialogDescription>
                            Outstanding {formatKes(bill.outstanding_cents)}.
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...BillPaymentController.store.form(bill.id)}
                        className="space-y-4"
                        onSuccess={() => setPaying(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="amount">
                                            Amount (KES)
                                        </Label>
                                        <Input
                                            id="amount"
                                            name="amount"
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            required
                                        />
                                        <InputError message={errors.amount} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="method">Method</Label>
                                        <select
                                            id="method"
                                            name="method"
                                            defaultValue="bank"
                                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                        >
                                            <option value="bank">
                                                Bank transfer
                                            </option>
                                            <option value="mpesa">
                                                M-Pesa
                                            </option>
                                            <option value="cheque">
                                                Cheque
                                            </option>
                                            <option value="cash">Cash</option>
                                            <option value="wht_certificate">
                                                WHT certificate
                                            </option>
                                        </select>
                                        <InputError message={errors.method} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="received_at">
                                            Received on
                                        </Label>
                                        <Input
                                            id="received_at"
                                            name="received_at"
                                            type="date"
                                            defaultValue={new Date()
                                                .toISOString()
                                                .slice(0, 10)}
                                            required
                                        />
                                        <InputError
                                            message={errors.received_at}
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="reference">
                                            Reference
                                        </Label>
                                        <Input
                                            id="reference"
                                            name="reference"
                                        />
                                    </div>
                                    <div className="grid gap-1.5 sm:col-span-2">
                                        <Label htmlFor="wht_certificate_reference">
                                            WHT certificate reference
                                        </Label>
                                        <Input
                                            id="wht_certificate_reference"
                                            name="wht_certificate_reference"
                                        />
                                    </div>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setPaying(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button disabled={processing}>
                                        Record
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Section({
    label,
    rows,
    blank = false,
}: {
    label: string;
    rows: BillLine[];
    blank?: boolean;
}) {
    return (
        <>
            {label && (
                <tr className="border-t">
                    <td
                        colSpan={6}
                        className="text-muted-foreground px-4 pt-3 pb-1 text-xs font-medium uppercase"
                    >
                        {label}
                    </td>
                </tr>
            )}
            {rows.map((l) => (
                <tr key={l.id} className="border-t">
                    <td className="px-4 py-2 whitespace-nowrap">
                        {l.dated_on ?? ''}
                    </td>
                    <td className="text-muted-foreground px-2 py-2">{l.seq}</td>
                    <td className="px-2 py-2">
                        {l.particulars}
                        {l.rule_reference && (
                            <div className="text-muted-foreground text-xs">
                                {l.rule_reference}
                                {l.provenance ? ` — ${l.provenance}` : ''}
                            </div>
                        )}
                    </td>
                    <td className="px-2 py-2 text-center text-xs">
                        {l.tax_type_code ?? ''}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                        {blank ? '' : formatAmount(l.claimed_cents)}
                    </td>
                    <td className="px-4 py-2 text-right tabular-nums">
                        {l.taxed_off_cents === null
                            ? ''
                            : formatAmount(l.taxed_off_cents)}
                    </td>
                </tr>
            ))}
        </>
    );
}

function Total({
    label,
    cents,
    muted = false,
}: {
    label: string;
    cents: number;
    muted?: boolean;
}) {
    return (
        <div
            className={`flex justify-between ${muted ? 'text-muted-foreground' : ''}`}
        >
            <dt>{label}</dt>
            <dd className="tabular-nums">{formatKes(cents)}</dd>
        </div>
    );
}

function Row({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right">{children}</dd>
        </div>
    );
}

BillShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Bills', href: index() },
        { title: props.bill.number ?? 'Draft', href: show(props.bill.id) },
    ],
});
