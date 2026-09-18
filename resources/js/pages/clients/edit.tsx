import { Form, Head } from '@inertiajs/react';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import { ClientFormFields } from '@/components/billing/client-form-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { edit, index } from '@/routes/clients';
import type { ClientForm } from '@/types/billing';

export default function ClientEdit({
    client,
}: {
    client: ClientForm & { id: string };
}) {
    return (
        <>
            <Head title={client.full_name} />
            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <Heading
                    title={client.full_name}
                    description="Client details"
                />
                <Card>
                    <CardContent>
                        <Form
                            {...ClientController.update.form(client.id)}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <ClientFormFields
                                        defaults={client}
                                        errors={errors}
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

ClientEdit.layout = (props: { client: { id: string; full_name: string } }) => ({
    breadcrumbs: [
        { title: 'Clients', href: index() },
        { title: props.client.full_name, href: edit(props.client.id) },
    ],
});
