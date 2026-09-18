import { Form, Head, Link } from '@inertiajs/react';
import PublishedAroVersionController from '@/actions/App/Http/Controllers/Admin/PublishedAroVersionController';
import ReviewedAroVersionController from '@/actions/App/Http/Controllers/Admin/ReviewedAroVersionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index, show } from '@/routes/admin/aro';

type Version = {
    id: string;
    code: string;
    legal_notice: string;
    status: string;
    source_url: string | null;
    source_sha256: string | null;
    effective_from: string | null;
    effective_to: string | null;
    items_count: number;
    modifiers_count: number;
    interpretations_count: number;
    reviewed_by: string | null;
    reviewed_at: string | null;
    published_by: string | null;
    published_at: string | null;
    can: { review: boolean; publish: boolean };
};

export default function AroIndex({ versions }: { versions: Version[] }) {
    return (
        <>
            <Head title="ARO catalogue" />
            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    title="Advocates (Remuneration) Order — catalogue"
                    description="The law as data. A version must be reviewed by one person and published by another before bills can be drawn on it."
                />
                {versions.map((v) => (
                    <Card key={v.id}>
                        <CardHeader>
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <CardTitle>
                                        <Link
                                            href={show(v.id)}
                                            className="hover:underline"
                                        >
                                            {v.code}
                                        </Link>{' '}
                                        <Badge
                                            variant={
                                                v.status === 'published'
                                                    ? 'default'
                                                    : v.status === 'reviewed'
                                                      ? 'secondary'
                                                      : 'outline'
                                            }
                                        >
                                            {v.status}
                                        </Badge>
                                    </CardTitle>
                                    <CardDescription>
                                        {v.legal_notice} · effective{' '}
                                        {v.effective_from}
                                        {v.effective_to
                                            ? ` to ${v.effective_to}`
                                            : ''}
                                    </CardDescription>
                                </div>
                                <div className="flex gap-2">
                                    {v.can.review && v.status === 'draft' && (
                                        <Form
                                            {...ReviewedAroVersionController.store.form(
                                                v.id,
                                            )}
                                        >
                                            {({ processing, errors }) => (
                                                <div>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        Mark reviewed
                                                    </Button>
                                                    <InputError
                                                        message={errors.review}
                                                    />
                                                </div>
                                            )}
                                        </Form>
                                    )}
                                    {v.can.publish &&
                                        v.status === 'reviewed' && (
                                            <Form
                                                {...PublishedAroVersionController.store.form(
                                                    v.id,
                                                )}
                                            >
                                                {({ processing, errors }) => (
                                                    <div>
                                                        <Button
                                                            size="sm"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Publish
                                                        </Button>
                                                        <InputError
                                                            message={
                                                                errors.publish
                                                            }
                                                        />
                                                    </div>
                                                )}
                                            </Form>
                                        )}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
                                <div>
                                    <dt className="text-muted-foreground text-xs uppercase">
                                        Heads
                                    </dt>
                                    <dd>
                                        {v.items_count} items ·{' '}
                                        {v.modifiers_count} modifiers ·{' '}
                                        {v.interpretations_count}{' '}
                                        interpretations
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-xs uppercase">
                                        Reviewed
                                    </dt>
                                    <dd>
                                        {v.reviewed_by
                                            ? `${v.reviewed_by} · ${v.reviewed_at}`
                                            : '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-xs uppercase">
                                        Published
                                    </dt>
                                    <dd>
                                        {v.published_by
                                            ? `${v.published_by} · ${v.published_at}`
                                            : '—'}
                                    </dd>
                                </div>
                                <div className="sm:col-span-3">
                                    <dt className="text-muted-foreground text-xs uppercase">
                                        Source
                                    </dt>
                                    <dd className="text-xs break-all">
                                        {v.source_url} · sha256{' '}
                                        {v.source_sha256}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}

AroIndex.layout = { breadcrumbs: [{ title: 'ARO catalogue', href: index() }] };
