import { useState } from 'react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ClientForm } from '@/types/billing';

export function ClientFormFields({
    defaults,
    errors,
}: {
    defaults: Partial<ClientForm>;
    errors: Record<string, string | undefined>;
}) {
    const [vatExempt, setVatExempt] = useState<boolean>(
        defaults.is_vat_exempt ?? false,
    );

    return (
        <div className="grid gap-5 sm:grid-cols-2">
            <div className="grid gap-1.5 sm:col-span-2">
                <Label htmlFor="full_name">Full name</Label>
                <Input
                    id="full_name"
                    name="full_name"
                    required
                    defaultValue={defaults.full_name ?? ''}
                />
                <InputError message={errors.full_name} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="client_type">Type</Label>
                <select
                    id="client_type"
                    name="client_type"
                    defaultValue={defaults.client_type ?? 'individual'}
                    className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                >
                    <option value="individual">Individual</option>
                    <option value="company">Company</option>
                    <option value="government">Government / public body</option>
                </select>
                <InputError message={errors.client_type} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="client_number">Client number</Label>
                <Input
                    id="client_number"
                    name="client_number"
                    defaultValue={defaults.client_number ?? ''}
                />
                <InputError message={errors.client_number} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="kra_pin">KRA PIN</Label>
                <Input
                    id="kra_pin"
                    name="kra_pin"
                    defaultValue={defaults.kra_pin ?? ''}
                    placeholder="Required for corporate clients claiming the expense"
                />
                <InputError message={errors.kra_pin} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="id_number">ID / registration number</Label>
                <Input
                    id="id_number"
                    name="id_number"
                    defaultValue={defaults.id_number ?? ''}
                />
                <InputError message={errors.id_number} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="email">Email</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    defaultValue={defaults.email ?? ''}
                />
                <InputError message={errors.email} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="phone">Phone</Label>
                <Input
                    id="phone"
                    name="phone"
                    defaultValue={defaults.phone ?? ''}
                />
                <InputError message={errors.phone} />
            </div>
            <div className="grid gap-1.5 sm:col-span-2">
                <Label htmlFor="address">Address</Label>
                <Input
                    id="address"
                    name="address"
                    defaultValue={defaults.address ?? ''}
                />
                <InputError message={errors.address} />
            </div>
            <div className="flex items-center gap-2">
                <input type="hidden" name="is_withholding_agent" value="0" />
                <Checkbox
                    id="is_withholding_agent"
                    name="is_withholding_agent"
                    value="1"
                    defaultChecked={defaults.is_withholding_agent ?? false}
                />
                <Label htmlFor="is_withholding_agent">
                    Withholding agent — bills carry a 5% WHT memo
                </Label>
            </div>
            <div className="flex items-center gap-2">
                <input type="hidden" name="is_vat_exempt" value="0" />
                <Checkbox
                    id="is_vat_exempt"
                    name="is_vat_exempt"
                    value="1"
                    defaultChecked={vatExempt}
                    onCheckedChange={(c) => setVatExempt(c === true)}
                />
                <Label htmlFor="is_vat_exempt">
                    VAT exempt (KRA exemption)
                </Label>
            </div>
            {vatExempt && (
                <div className="grid gap-1.5 sm:col-span-2">
                    <Label htmlFor="vat_exemption_reference">
                        Exemption reference (printed on the invoice)
                    </Label>
                    <Input
                        id="vat_exemption_reference"
                        name="vat_exemption_reference"
                        defaultValue={defaults.vat_exemption_reference ?? ''}
                    />
                    <InputError message={errors.vat_exemption_reference} />
                </div>
            )}
        </div>
    );
}
