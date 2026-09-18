import { Form, Head } from '@inertiajs/react';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import {
    MatterFormFields,
    type MatterFormValues,
} from '@/components/billing/matter-form-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { edit, index, show } from '@/routes/matters';
import type { CourtLevelOption } from '@/types/billing';

type Props = {
    matter: MatterFormValues & { id: string };
    clients: { id: string; full_name: string }[];
    courtLevels: CourtLevelOption[];
};

export default function MatterEdit({ matter, clients, courtLevels }: Props) {
    return (
        <>
            <Head title={`Edit ${matter.title}`} />
            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <Heading title={matter.title} description="Matter details" />
                <Card>
                    <CardContent>
                        <Form
                            {...MatterController.update.form(matter.id)}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <MatterFormFields
                                        defaults={{
                                            ...matter,
                                            status: matter.status ?? 'open',
                                        }}
                                        errors={errors}
                                        clients={clients}
                                        courtLevels={courtLevels}
                                    />
                                    <Button disabled={processing}>Save</Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

MatterEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Matters', href: index() },
        { title: props.matter.title, href: show(props.matter.id) },
        { title: 'Edit', href: edit(props.matter.id) },
    ],
});
