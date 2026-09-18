import { Form, Head } from '@inertiajs/react';
import FirmController from '@/actions/App/Http/Controllers/FirmController';
import { FirmFormFields } from '@/components/billing/firm-form-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function FirmCreate({ hasFirm }: { hasFirm: boolean }) {
    return (
        <>
            <Head title="Set up your firm" />
            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <Heading
                    title={hasFirm ? 'Add another firm' : 'Set up your firm'}
                    description="Every client, matter and bill belongs to a firm. You become its owner."
                />
                <Card>
                    <CardContent>
                        <Form
                            {...FirmController.store.form()}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FirmFormFields
                                        defaults={{}}
                                        errors={errors}
                                    />
                                    <Button disabled={processing}>
                                        Create firm
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
