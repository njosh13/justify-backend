import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CourtLevelOption } from '@/types/billing';

export type MatterFormValues = {
    client_id: string;
    title: string;
    reference: string | null;
    court_level: string;
    cause_number: string | null;
    description: string | null;
    value: number | string | null;
    status?: string;
    opened_on: string | null;
};

export function MatterFormFields({
    defaults,
    errors,
    clients,
    courtLevels,
}: {
    defaults: Partial<MatterFormValues>;
    errors: Record<string, string | undefined>;
    clients: { id: string; full_name: string }[];
    courtLevels: CourtLevelOption[];
}) {
    return (
        <div className="grid gap-5 sm:grid-cols-2">
            <div className="grid gap-1.5 sm:col-span-2">
                <Label htmlFor="client_id">Client</Label>
                <select
                    id="client_id"
                    name="client_id"
                    required
                    defaultValue={defaults.client_id ?? ''}
                    className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                >
                    <option value="" disabled>
                        Choose a client…
                    </option>
                    {clients.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.full_name}
                        </option>
                    ))}
                </select>
                <InputError message={errors.client_id} />
            </div>
            <div className="grid gap-1.5 sm:col-span-2">
                <Label htmlFor="title">Matter title</Label>
                <Input
                    id="title"
                    name="title"
                    required
                    defaultValue={defaults.title ?? ''}
                    placeholder="Sale of LR No. 1234/56 to Wanjiku"
                />
                <InputError message={errors.title} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="reference">File reference</Label>
                <Input
                    id="reference"
                    name="reference"
                    defaultValue={defaults.reference ?? ''}
                    placeholder="CONV/2026/014"
                />
                <InputError message={errors.reference} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="court_level">Forum</Label>
                <select
                    id="court_level"
                    name="court_level"
                    required
                    defaultValue={defaults.court_level ?? 'none'}
                    className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                >
                    {courtLevels.map((c) => (
                        <option key={c.value} value={c.value}>
                            {c.label}
                            {c.schedule ? ` — Schedule ${c.schedule}` : ''}
                        </option>
                    ))}
                </select>
                <InputError message={errors.court_level} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="cause_number">Cause number</Label>
                <Input
                    id="cause_number"
                    name="cause_number"
                    defaultValue={defaults.cause_number ?? ''}
                    placeholder="HCCC 123 of 2026"
                />
                <InputError message={errors.cause_number} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="value">Value of subject matter (KES)</Label>
                <Input
                    id="value"
                    name="value"
                    type="number"
                    min={0}
                    step="0.01"
                    defaultValue={defaults.value ?? ''}
                    placeholder="Consideration, sum sued, annual rent…"
                />
                <p className="text-muted-foreground text-xs">
                    Para 21: price in the deed, else stamp-duty value,
                    estate-duty value, last sale within ten years, then market
                    value. Refine on the classification.
                </p>
                <InputError message={errors.value} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="opened_on">Opened on</Label>
                <Input
                    id="opened_on"
                    name="opened_on"
                    type="date"
                    defaultValue={
                        defaults.opened_on ??
                        new Date().toISOString().slice(0, 10)
                    }
                />
                <InputError message={errors.opened_on} />
            </div>
            {defaults.status !== undefined && (
                <div className="grid gap-1.5">
                    <Label htmlFor="status">Status</Label>
                    <select
                        id="status"
                        name="status"
                        defaultValue={defaults.status}
                        className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                    >
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
            )}
            <div className="grid gap-1.5 sm:col-span-2">
                <Label htmlFor="description">Notes</Label>
                <Input
                    id="description"
                    name="description"
                    defaultValue={defaults.description ?? ''}
                />
                <InputError message={errors.description} />
            </div>
        </div>
    );
}
