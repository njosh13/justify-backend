import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import FeeAgreementController from '@/actions/App/Http/Controllers/FeeAgreementController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatKes } from '@/lib/money';
import type { FeeAgreementRow } from '@/types/billing';

export function FeeAgreementsCard({
    matterId,
    agreements,
    canEdit,
}: {
    matterId: string;
    agreements: FeeAgreementRow[];
    canEdit: boolean;
}) {
    const [adding, setAdding] = useState(false);
    const [type, setType] = useState('hourly');

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <CardTitle>Fee agreements</CardTitle>
                        <CardDescription>
                            Agreed hourly rate (Sch 5 Part I) or a para 22
                            election to charge under Schedule 5 — the election
                            must be communicated in writing before or with the
                            bill.
                        </CardDescription>
                    </div>
                    {canEdit && !adding && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setAdding(true)}
                        >
                            Add
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {agreements.length === 0 && !adding && (
                    <p className="text-muted-foreground text-sm">
                        None. Scale fees apply.
                    </p>
                )}
                {agreements.map((a) => (
                    <div
                        key={a.id}
                        className="flex items-start justify-between gap-2 rounded-md border p-3 text-sm"
                    >
                        <div>
                            <div className="font-medium">{a.type_label}</div>
                            <div className="text-muted-foreground text-xs">
                                {a.hourly_rate_cents !== null && (
                                    <>
                                        Rate {formatKes(a.hourly_rate_cents)}
                                        /hour ·{' '}
                                    </>
                                )}
                                {a.fixed_amount_cents !== null && (
                                    <>
                                        Fixed {formatKes(a.fixed_amount_cents)}{' '}
                                        ·{' '}
                                    </>
                                )}
                                {a.type === 'schedule5_election' &&
                                    (a.election_communicated_at ? (
                                        <Badge variant="secondary">
                                            communicated{' '}
                                            {a.election_communicated_at}
                                        </Badge>
                                    ) : (
                                        <Badge variant="destructive">
                                            not yet communicated in writing
                                        </Badge>
                                    ))}
                                {a.signed_at && <> · signed {a.signed_at}</>}
                            </div>
                            {a.notes && (
                                <div className="mt-1 text-xs">{a.notes}</div>
                            )}
                        </div>
                        {canEdit && (
                            <Link
                                href={FeeAgreementController.destroy({
                                    matter: matterId,
                                    feeAgreement: a.id,
                                })}
                                method="delete"
                                as="button"
                                className="text-muted-foreground text-xs hover:underline"
                            >
                                Withdraw
                            </Link>
                        )}
                    </div>
                ))}

                {adding && canEdit && (
                    <Form
                        {...FeeAgreementController.store.form(matterId)}
                        className="space-y-4 rounded-md border border-dashed p-3"
                        onSuccess={() => setAdding(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="fa-type">Type</Label>
                                        <select
                                            id="fa-type"
                                            name="type"
                                            value={type}
                                            onChange={(e) =>
                                                setType(e.target.value)
                                            }
                                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                        >
                                            <option value="hourly">
                                                Agreed hourly rate (Sch 5 Part
                                                I)
                                            </option>
                                            <option value="schedule5_election">
                                                Para 22 election to Schedule 5
                                            </option>
                                            <option value="fixed">
                                                Fixed fee
                                            </option>
                                            <option value="scale">
                                                Scale fees
                                            </option>
                                        </select>
                                        <InputError message={errors.type} />
                                    </div>
                                    {type === 'hourly' && (
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="hourly_rate">
                                                Hourly rate (KES)
                                            </Label>
                                            <Input
                                                id="hourly_rate"
                                                name="hourly_rate"
                                                type="number"
                                                min={0}
                                                step="0.01"
                                            />
                                            <InputError
                                                message={errors.hourly_rate}
                                            />
                                        </div>
                                    )}
                                    {type === 'fixed' && (
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="fixed_amount">
                                                Fixed amount (KES)
                                            </Label>
                                            <Input
                                                id="fixed_amount"
                                                name="fixed_amount"
                                                type="number"
                                                min={0}
                                                step="0.01"
                                            />
                                            <InputError
                                                message={errors.fixed_amount}
                                            />
                                        </div>
                                    )}
                                    {type === 'schedule5_election' && (
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="election_communicated_at">
                                                Communicated to client in
                                                writing on
                                            </Label>
                                            <Input
                                                id="election_communicated_at"
                                                name="election_communicated_at"
                                                type="date"
                                            />
                                            <InputError
                                                message={
                                                    errors.election_communicated_at
                                                }
                                            />
                                        </div>
                                    )}
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="signed_at">
                                            Signed on
                                        </Label>
                                        <Input
                                            id="signed_at"
                                            name="signed_at"
                                            type="date"
                                        />
                                    </div>
                                    <div className="grid gap-1.5 sm:col-span-2">
                                        <Label htmlFor="fa-notes">Notes</Label>
                                        <Input id="fa-notes" name="notes" />
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <Button disabled={processing} size="sm">
                                        Record
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setAdding(false)}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </CardContent>
        </Card>
    );
}
