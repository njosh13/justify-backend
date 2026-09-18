import { Form, Head } from '@inertiajs/react';
import FirmController from '@/actions/App/Http/Controllers/FirmController';
import {
    FirmFormFields,
    type FirmFormValues,
} from '@/components/billing/firm-form-fields';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/firm';

type Member = { id: number; name: string; email: string; role: string | null };

export default function FirmSettings({
    firm,
    members,
}: {
    firm: FirmFormValues & { id: string; bill_sequence: number };
    members: Member[];
}) {
    return (
        <>
            <Head title="Firm settings" />
            <div className="space-y-8">
                <Heading
                    variant="small"
                    title="Firm"
                    description="Details printed on bills, VAT status, rounding and numbering."
                />

                <Form
                    {...FirmController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <FirmFormFields defaults={firm} errors={errors} />
                            <p className="text-muted-foreground text-xs">
                                Next bill number: {firm.bill_number_prefix}/
                                {new Date().getFullYear()}/
                                {String(firm.bill_sequence + 1).padStart(
                                    4,
                                    '0',
                                )}
                            </p>
                            <Button disabled={processing}>Save</Button>
                        </>
                    )}
                </Form>

                <div className="space-y-3">
                    <Heading
                        variant="small"
                        title="Members"
                        description="Roles decide who may bill, publish ARO data and record payments."
                    />
                    <table className="w-full text-sm">
                        <tbody>
                            {members.map((m) => (
                                <tr
                                    key={m.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-2">
                                        {m.name}
                                        <div className="text-muted-foreground text-xs">
                                            {m.email}
                                        </div>
                                    </td>
                                    <td className="py-2 text-right">
                                        <Badge variant="outline">
                                            {m.role ?? '—'}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

FirmSettings.layout = {
    breadcrumbs: [{ title: 'Firm settings', href: edit() }],
};
