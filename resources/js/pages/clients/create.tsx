import { Form, Head } from '@inertiajs/react';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import { ClientFormFields } from '@/components/billing/client-form-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, index } from '@/routes/clients';

export default function ClientCreate() {
    return (
        <>
            <Head title="New client" />
            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <Heading title="New client" />
                <Card>
                    <CardContent>
                        <Form
                            {...ClientController.store.form()}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <ClientFormFields
                                        defaults={{}}
                                        errors={errors}
                                    />
                                    <Button disabled={processing}>
                                        Save client
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ClientCreate.layout = {
    breadcrumbs: [
        { title: 'Clients', href: index() },
        { title: 'New', href: create() },
    ],
};
