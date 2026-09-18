import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

export type FirmFormValues = {
    name: string;
    kra_pin: string | null;
    lsk_firm_number: string | null;
    address: string | null;
    email: string | null;
    phone: string | null;
    vat_registered: boolean;
    rounding_policy: string;
    default_cost_basis: string;
    bill_number_prefix: string;
};

export function FirmFormFields({
    defaults,
    errors,
}: {
    defaults: Partial<FirmFormValues>;
    errors: Record<string, string | undefined>;
}) {
    return (
        <div className="grid gap-5 sm:grid-cols-2">
            <div className="grid gap-1.5 sm:col-span-2">
                <Label htmlFor="name">Firm name</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    defaultValue={defaults.name ?? ''}
                    placeholder="Kimondo & Co. Advocates"
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="kra_pin">KRA PIN</Label>
                <Input
                    id="kra_pin"
                    name="kra_pin"
                    defaultValue={defaults.kra_pin ?? ''}
                    placeholder="P051234567X"
                />
                <InputError message={errors.kra_pin} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="lsk_firm_number">LSK firm number</Label>
                <Input
                    id="lsk_firm_number"
                    name="lsk_firm_number"
                    defaultValue={defaults.lsk_firm_number ?? ''}
                />
                <InputError message={errors.lsk_firm_number} />
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
                <Label htmlFor="address">Address (printed on bills)</Label>
                <Input
                    id="address"
                    name="address"
                    defaultValue={defaults.address ?? ''}
                />
                <InputError message={errors.address} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="bill_number_prefix">Bill number prefix</Label>
                <Input
                    id="bill_number_prefix"
                    name="bill_number_prefix"
                    required
                    defaultValue={defaults.bill_number_prefix ?? 'FN'}
                />
                <p className="text-muted-foreground text-xs">
                    Bills are numbered PREFIX/YYYY/0001 per firm.
                </p>
                <InputError message={errors.bill_number_prefix} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="rounding_policy">Line rounding (I-7)</Label>
                <select
                    id="rounding_policy"
                    name="rounding_policy"
                    defaultValue={
                        defaults.rounding_policy ?? 'shilling_half_up'
                    }
                    className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                >
                    <option value="shilling_half_up">
                        Round each line to the shilling (half up)
                    </option>
                    <option value="cent">Keep cents</option>
                </select>
                <InputError message={errors.rounding_policy} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="default_cost_basis">
                    Default cost basis for fee lines
                </Label>
                <select
                    id="default_cost_basis"
                    name="default_cost_basis"
                    defaultValue={
                        defaults.default_cost_basis ?? 'advocate_client'
                    }
                    className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                >
                    <option value="advocate_client">
                        Advocate and client (Part B, +50%)
                    </option>
                    <option value="party_party">
                        Party and party (Part A)
                    </option>
                </select>
                <InputError message={errors.default_cost_basis} />
            </div>
            <div className="flex items-center gap-2 self-end pb-2">
                <input type="hidden" name="vat_registered" value="0" />
                <Checkbox
                    id="vat_registered"
                    name="vat_registered"
                    value="1"
                    defaultChecked={defaults.vat_registered ?? true}
                />
                <Label htmlFor="vat_registered">
                    VAT registered — charge 16% on fees and recharges
                </Label>
                <InputError message={errors.vat_registered} />
            </div>
        </div>
    );
}
