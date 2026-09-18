import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CatalogueItem, CostBasis, Posture } from '@/types/billing';

export type FeeInputValues = {
    basis: string;
    quantity: string;
    scale: string;
    posture: string;
    modifier_codes: string[];
    modifier_amounts: Record<string, string>;
    certificates: { two_advocates: boolean; senior_counsel: boolean };
    cost_basis: CostBasis;
    agreed_rate: string;
    instruction_fee: string;
    contested: boolean;
};

export const emptyFeeInputs = (
    costBasis: CostBasis = 'advocate_client',
): FeeInputValues => ({
    basis: '',
    quantity: '',
    scale: '',
    posture: '',
    modifier_codes: [],
    modifier_amounts: {},
    certificates: { two_advocates: false, senior_counsel: false },
    cost_basis: costBasis,
    agreed_rate: '',
    instruction_fee: '',
    contested: false,
});

const posturesFor = (
    table: string | null,
): { value: Posture; label: string }[] => {
    if (table === 'a') {
        return [
            { value: 'undefended', label: 'Undefended (table a)' },
            { value: 'no_appearance', label: 'No appearance entered — 65%' },
        ];
    }

    return [
        { value: 'full_trial', label: 'Full trial (table b)' },
        { value: 'summary', label: 'Determined summarily — 75%' },
        {
            value: 'settled_pre_hearing',
            label: 'Settled before first hearing — 85%',
        },
    ];
};

const basisLabel: Record<string, string> = {
    consideration: 'Consideration / value (KES)',
    amount_secured: 'Amount secured (KES)',
    annual_rent: 'Annual rent (KES)',
    sum_sued: 'Sum sued or found due (KES)',
    gross_estate: 'Gross value of estate (KES)',
    net_estate: 'Net estate (KES)',
    debt: 'Debt (KES)',
    value: 'Value of subject matter (KES)',
    net_capital: 'Net capital value (KES)',
    income: 'Income (KES)',
    capital: 'Capital realised or invested (KES)',
};

/**
 * The inputs a fee head needs, driven by the catalogue's `needs` map: basis,
 * quantity, scale, posture (bound to its table), modifiers, certificates,
 * cost basis, agreed rate, instruction fee, contested.
 */
export function FeeInputs({
    item,
    values,
    onChange,
    hide = [],
    contentiousHint,
}: {
    item: CatalogueItem;
    values: FeeInputValues;
    onChange: (next: FeeInputValues) => void;
    hide?: (keyof FeeInputValues)[];
    contentiousHint?: string;
}) {
    const set = <K extends keyof FeeInputValues>(
        key: K,
        value: FeeInputValues[K],
    ) => onChange({ ...values, [key]: value });
    const needs = item.needs;
    const postureTable =
        typeof needs.posture_table === 'string'
            ? needs.posture_table
            : needs.posture_table && values.scale
              ? (needs.posture_table[values.scale] ?? null)
              : null;
    const show = (k: keyof FeeInputValues) => !hide.includes(k);

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {needs.basis && show('basis') && (
                <div className="grid gap-1.5">
                    <Label htmlFor="fi-basis">
                        {basisLabel[item.basis_type ?? ''] ??
                            'Subject-matter value (KES)'}
                    </Label>
                    <Input
                        id="fi-basis"
                        type="number"
                        inputMode="decimal"
                        min={0}
                        step="0.01"
                        value={values.basis}
                        onChange={(e) => set('basis', e.target.value)}
                        placeholder="e.g. 10000000"
                    />
                </div>
            )}

            {needs.quantity && show('quantity') && (
                <div className="grid gap-1.5">
                    <Label htmlFor="fi-qty">
                        Quantity{item.unit_label ? ` (${item.unit_label})` : ''}
                        {needs.quantity_required ? '' : ' — optional'}
                    </Label>
                    <Input
                        id="fi-qty"
                        type="number"
                        inputMode="decimal"
                        min={0}
                        step="0.25"
                        value={values.quantity}
                        onChange={(e) => set('quantity', e.target.value)}
                        placeholder={needs.quantity_required ? 'required' : '1'}
                    />
                </div>
            )}

            {needs.scale && show('scale') && (
                <div className="grid gap-1.5">
                    <Label>Scale</Label>
                    <Select
                        value={values.scale}
                        onValueChange={(v) =>
                            onChange({ ...values, scale: v, posture: '' })
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Lower or higher scale" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="lower">
                                Lower — no defence or denial of liability filed
                            </SelectItem>
                            <SelectItem value="higher">
                                Higher — defended / all other cases
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            )}

            {needs.posture && show('posture') && (
                <div className="grid gap-1.5">
                    <Label>Posture (Sch 6/7 item 1 a–c)</Label>
                    <Select
                        value={values.posture}
                        onValueChange={(v) => set('posture', v)}
                        disabled={needs.scale && !values.scale}
                    >
                        <SelectTrigger>
                            <SelectValue
                                placeholder={
                                    needs.scale && !values.scale
                                        ? 'Choose the scale first'
                                        : 'Default for this table'
                                }
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {posturesFor(postureTable).map((p) => (
                                <SelectItem key={p.value} value={p.value}>
                                    {p.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}

            {needs.agreed_rate && show('agreed_rate') && (
                <div className="grid gap-1.5">
                    <Label htmlFor="fi-rate">Agreed hourly rate (KES)</Label>
                    <Input
                        id="fi-rate"
                        type="number"
                        min={0}
                        step="0.01"
                        value={values.agreed_rate}
                        onChange={(e) => set('agreed_rate', e.target.value)}
                    />
                </div>
            )}

            {needs.instruction_fee && show('instruction_fee') && (
                <div className="grid gap-1.5">
                    <Label htmlFor="fi-instr">
                        Instruction fee allowed (KES)
                    </Label>
                    <Input
                        id="fi-instr"
                        type="number"
                        min={0}
                        step="0.01"
                        value={values.instruction_fee}
                        onChange={(e) => set('instruction_fee', e.target.value)}
                    />
                </div>
            )}

            {item.applies_cost_basis !== 'non_contentious' &&
                show('cost_basis') && (
                    <div className="grid gap-1.5">
                        <Label>Cost basis</Label>
                        <Select
                            value={values.cost_basis}
                            onValueChange={(v) =>
                                set('cost_basis', v as CostBasis)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="party_party">
                                    Party and party (Part A)
                                </SelectItem>
                                <SelectItem value="advocate_client">
                                    Advocate and client (Part B, +50%)
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        {contentiousHint && (
                            <p className="text-muted-foreground text-xs">
                                {contentiousHint}
                            </p>
                        )}
                    </div>
                )}

            {(needs.certificates || needs.contested) && (
                <div className="grid gap-2 sm:col-span-2">
                    {needs.certificates && show('certificates') && (
                        <>
                            <Label className="text-muted-foreground text-xs uppercase">
                                Judge's certificates (Sch 6 provisos)
                            </Label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={values.certificates.two_advocates}
                                    onCheckedChange={(c) =>
                                        set('certificates', {
                                            ...values.certificates,
                                            two_advocates: c === true,
                                        })
                                    }
                                />
                                Two advocates — instruction fee doubled (proviso
                                ii, para 59)
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={values.certificates.senior_counsel}
                                    onCheckedChange={(c) =>
                                        set('certificates', {
                                            ...values.certificates,
                                            senior_counsel: c === true,
                                        })
                                    }
                                />
                                Senior counsel — instruction fee increased by
                                one-half (proviso iii, para 60)
                            </label>
                        </>
                    )}
                    {needs.contested && show('contested') && (
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={values.contested}
                                onCheckedChange={(c) =>
                                    set('contested', c === true)
                                }
                            />
                            Matter is contested (Sch 10 Part B applies only in
                            contested matters)
                        </label>
                    )}
                </div>
            )}

            {item.modifiers.length > 0 && show('modifier_codes') && (
                <div className="grid gap-2 sm:col-span-2">
                    <Label className="text-muted-foreground text-xs uppercase">
                        Adjustments from the Order
                    </Label>
                    {item.modifiers.map((m) => {
                        const on = values.modifier_codes.includes(m.code);

                        return (
                            <div
                                key={m.code}
                                className="flex flex-wrap items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    checked={on}
                                    onCheckedChange={(c) =>
                                        set(
                                            'modifier_codes',
                                            c === true
                                                ? [
                                                      ...values.modifier_codes,
                                                      m.code,
                                                  ]
                                                : values.modifier_codes.filter(
                                                      (x) => x !== m.code,
                                                  ),
                                        )
                                    }
                                />
                                <span className="min-w-0 flex-1">
                                    {m.label}{' '}
                                    <span className="text-muted-foreground text-xs">
                                        ({m.rule_reference}; {m.op}{' '}
                                        {m.op === 'add' ||
                                        m.op === 'floor' ||
                                        m.op === 'cap'
                                            ? formatModifierMoney(m.value)
                                            : m.value}
                                        )
                                    </span>
                                </span>
                                {on && m.needs_amount && (
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        className="w-40"
                                        placeholder="Amount (KES)"
                                        value={
                                            values.modifier_amounts[m.code] ??
                                            ''
                                        }
                                        onChange={(e) =>
                                            set('modifier_amounts', {
                                                ...values.modifier_amounts,
                                                [m.code]: e.target.value,
                                            })
                                        }
                                    />
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function formatModifierMoney(cents: string): string {
    const n = Number(cents);

    return Number.isFinite(n)
        ? `KES ${(n / 100).toLocaleString('en-KE')}`
        : cents;
}

/** Turns the form values into the JSON body `POST /aro/preview` and the chargeable-item form share. */
export function feeInputsToPayload(
    values: FeeInputValues,
): Record<string, unknown> {
    return {
        basis: values.basis === '' ? null : values.basis,
        quantity: values.quantity === '' ? null : values.quantity,
        scale: values.scale || null,
        posture: values.posture || null,
        modifier_codes: values.modifier_codes,
        modifier_amounts: Object.fromEntries(
            Object.entries(values.modifier_amounts).filter(([, v]) => v !== ''),
        ),
        certificates: values.certificates,
        cost_basis: values.cost_basis,
        agreed_rate: values.agreed_rate === '' ? null : values.agreed_rate,
        instruction_fee:
            values.instruction_fee === '' ? null : values.instruction_fee,
        contested: values.contested,
    };
}
