import { Form, Head } from '@inertiajs/react';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import { MatterFormFields } from '@/components/billing/matter-form-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, index } from '@/routes/matters';
import type { CourtLevelOption } from '@/types/billing';

export default function MatterCreate({
    clients,
    courtLevels,
}: {
    clients: { id: string; full_name: string }[];
    courtLevels: CourtLevelOption[];
}) {
    return (
        <>
            <Head title="New matter" />
            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <Heading
                    title="Open a matter"
                    description="The forum decides which schedule of the Order governs litigation costs."
                />
                <Card>
                    <CardContent>
                        <Form
                            {...MatterController.store.form()}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <MatterFormFields
                                        defaults={{}}
                                        errors={errors}
                                        clients={clients}
                                        courtLevels={courtLevels}
                                    />
                                    <Button disabled={processing}>
                                        Open matter
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

MatterCreate.layout = {
    breadcrumbs: [
        { title: 'Matters', href: index() },
        { title: 'New', href: create() },
    ],
};
