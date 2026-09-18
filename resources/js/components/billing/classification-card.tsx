import { Form } from '@inertiajs/react';
import { useState } from 'react';
import MatterClassificationController from '@/actions/App/Http/Controllers/MatterClassificationController';
import { FeeHeadPicker } from '@/components/billing/fee-head-picker';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { centsToInput, formatKes, labelFor } from '@/lib/money';
import type { CatalogueItem, Classification } from '@/types/billing';

const limbs = [
    ['deed_price', 'Price or consideration in the deed'],
    ['stamp_duty_value', 'Value fixed for stamp duty'],
    ['estate_duty_value', 'Value passed for estate duty'],
    ['last_sale_10y', 'Last sale within ten years'],
    ['market_value_3y', 'Average market value, preceding three years'],
    ['sum_sued', 'Sum sued for'],
    ['sum_found_due', 'Sum found due'],
    ['annual_rent', 'Annual rent (highest if varying)'],
    ['gross_estate', 'Gross capital value of the estate'],
    ['net_estate', 'Net estate'],
];

export function ClassificationCard({
    matterId,
    classification,
    catalogue,
    schedule,
    canEdit,
}: {
    matterId: string;
    classification: Classification | null;
    catalogue: CatalogueItem[];
    schedule: number | null;
    canEdit: boolean;
}) {
    const [editing, setEditing] = useState(classification === null);
    const [item, setItem] = useState<CatalogueItem | null>(
        catalogue.find((i) => i.id === classification?.aro_item_id) ?? null,
    );
    const [exempt, setExempt] = useState(classification?.is_exempt ?? false);

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <CardTitle>Classification</CardTitle>
                        <CardDescription>
                            Primary head, subject-matter value (para 21), scale,
                            posture and certificates. Every fee line inherits
                            these unless overridden.
                        </CardDescription>
                    </div>
                    {canEdit && classification && !editing && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setEditing(true)}
                        >
                            Edit
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {!editing && classification && (
                    <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <Row label="Primary head">
                            {classification.item ? (
                                <>
                                    <span className="font-mono text-xs">
                                        {classification.item.code}
                                    </span>{' '}
                                    · {classification.item.label}
                                </>
                            ) : (
                                '—'
                            )}
                        </Row>
                        <Row label="Basis">
                            {formatKes(classification.basis_cents)}{' '}
                            <span className="text-muted-foreground text-xs">
                                {classification.basis_limb
                                    ? `(${labelFor(classification.basis_limb)})`
                                    : ''}
                            </span>
                        </Row>
                        <Row label="Scale">
                            {labelFor(classification.scale)}
                        </Row>
                        <Row label="Posture">
                            {labelFor(classification.posture)}
                        </Row>
                        <Row label="Certificates">
                            {Object.entries(classification.certificates ?? {})
                                .filter(([, v]) => v)
                                .map(([k]) => (
                                    <Badge
                                        key={k}
                                        variant="secondary"
                                        className="mr-1"
                                    >
                                        {labelFor(k)}
                                    </Badge>
                                ))}
                            {Object.values(
                                classification.certificates ?? {},
                            ).every((v) => !v) && '—'}
                        </Row>
                        <Row label="Contested">
                            {classification.contested ? 'Yes' : 'No'}
                        </Row>
                        {classification.is_exempt && (
                            <Row label="Scale exemption">
                                <Badge variant="destructive">Exempt</Badge>{' '}
                                <span className="text-xs">
                                    {classification.exemption_reason}
                                </span>
                            </Row>
                        )}
                    </dl>
                )}

                {editing && canEdit && (
                    <Form
                        {...MatterClassificationController.update.form(
                            matterId,
                        )}
                        className="space-y-5"
                        onSuccess={() => setEditing(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="aro_item_id"
                                    value={item?.id ?? ''}
                                />
                                <div className="space-y-1.5">
                                    <Label>Primary head of the Order</Label>
                                    <FeeHeadPicker
                                        catalogue={catalogue.filter(
                                            (c) => c.computation !== 'pointer',
                                        )}
                                        value={item?.id ?? null}
                                        onChange={setItem}
                                        preferredSchedule={schedule}
                                    />
                                    <InputError message={errors.aro_item_id} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="basis">
                                            Subject-matter value (KES)
                                        </Label>
                                        <Input
                                            id="basis"
                                            name="basis"
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            defaultValue={centsToInput(
                                                classification?.basis_cents,
                                            )}
                                        />
                                        <InputError message={errors.basis} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="basis_limb">
                                            How the value was fixed (para 21)
                                        </Label>
                                        <select
                                            id="basis_limb"
                                            name="basis_limb"
                                            defaultValue={
                                                classification?.basis_limb ?? ''
                                            }
                                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                        >
                                            <option value="">—</option>
                                            {limbs.map(([v, l]) => (
                                                <option key={v} value={v}>
                                                    {l}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="scale">
                                            Scale (Sch 7/8/9/11)
                                        </Label>
                                        <select
                                            id="scale"
                                            name="scale"
                                            defaultValue={
                                                classification?.scale ?? ''
                                            }
                                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                        >
                                            <option value="">—</option>
                                            <option value="lower">
                                                Lower — no defence filed / ex
                                                parte / consent
                                            </option>
                                            <option value="higher">
                                                Higher — defended
                                            </option>
                                        </select>
                                        <InputError message={errors.scale} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="posture">
                                            Posture (Sch 6/7 item 1)
                                        </Label>
                                        <select
                                            id="posture"
                                            name="posture"
                                            defaultValue={
                                                classification?.posture ?? ''
                                            }
                                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                        >
                                            <option value="">
                                                Default for the table
                                            </option>
                                            <option value="undefended">
                                                Undefended
                                            </option>
                                            <option value="no_appearance">
                                                No appearance entered (65% of a)
                                            </option>
                                            <option value="full_trial">
                                                Full trial
                                            </option>
                                            <option value="summary">
                                                Determined summarily (75% of b)
                                            </option>
                                            <option value="settled_pre_hearing">
                                                Settled before first hearing
                                                (85% of b)
                                            </option>
                                        </select>
                                        <InputError message={errors.posture} />
                                    </div>
                                </div>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <Check
                                        name="certificates[two_advocates]"
                                        label="Certificate for two advocates (para 59)"
                                        defaultChecked={
                                            classification?.certificates
                                                ?.two_advocates
                                        }
                                    />
                                    <Check
                                        name="certificates[senior_counsel]"
                                        label="Certificate for senior counsel (para 60)"
                                        defaultChecked={
                                            classification?.certificates
                                                ?.senior_counsel
                                        }
                                    />
                                    <Check
                                        name="certificates[higher_scale_order]"
                                        label="Order for the higher scale (para 50A)"
                                        defaultChecked={
                                            classification?.certificates
                                                ?.higher_scale_order
                                        }
                                    />
                                    <Check
                                        name="contested"
                                        label="Matter is contested (Sch 10 Part B)"
                                        defaultChecked={
                                            classification?.contested
                                        }
                                    />
                                </div>
                                <div className="space-y-2 rounded-md border border-dashed p-3">
                                    <Check
                                        name="is_exempt"
                                        label="Exempt from scale minimums (pro bono, legal aid) — para 3 enforcement is switched off for this matter"
                                        defaultChecked={exempt}
                                        onChange={setExempt}
                                    />
                                    {exempt && (
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="exemption_reason">
                                                Reason (recorded with your name
                                                and the date)
                                            </Label>
                                            <Input
                                                id="exemption_reason"
                                                name="exemption_reason"
                                                defaultValue={
                                                    classification?.exemption_reason ??
                                                    ''
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors.exemption_reason
                                                }
                                            />
                                        </div>
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    <Button disabled={processing}>
                                        Save classification
                                    </Button>
                                    {classification && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() => setEditing(false)}
                                        >
                                            Cancel
                                        </Button>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                )}

                {!classification && !canEdit && (
                    <p className="text-muted-foreground text-sm">
                        Not classified yet.
                    </p>
                )}
            </CardContent>
        </Card>
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
        <div>
            <dt className="text-muted-foreground text-xs uppercase">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

function Check({
    name,
    label,
    defaultChecked,
    onChange,
}: {
    name: string;
    label: string;
    defaultChecked?: boolean;
    onChange?: (v: boolean) => void;
}) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <input type="hidden" name={name} value="0" />
            <Checkbox
                name={name}
                value="1"
                defaultChecked={defaultChecked ?? false}
                onCheckedChange={(c) => onChange?.(c === true)}
            />
            {label}
        </label>
    );
}
