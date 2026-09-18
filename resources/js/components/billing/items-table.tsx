import { Link } from '@inertiajs/react';
import { useState } from 'react';
import ChargeableItemController from '@/actions/App/Http/Controllers/ChargeableItemController';
import { ComputedSteps } from '@/components/billing/computed-steps';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { formatAmount, formatKes, labelFor } from '@/lib/money';
import { show as billShow } from '@/routes/bills';
import type { ChargeableItemRow } from '@/types/billing';

export function ItemsTable({
    matterId,
    items,
    canEdit,
    selected,
    onToggle,
    onEdit,
}: {
    matterId: string;
    items: ChargeableItemRow[];
    canEdit: boolean;
    selected: Set<string>;
    onToggle: (id: string, on: boolean) => void;
    onEdit: (item: ChargeableItemRow) => void;
}) {
    const [expanded, setExpanded] = useState<string | null>(null);

    if (items.length === 0) {
        return (
            <p className="text-muted-foreground p-4 text-sm">
                No chargeable work yet.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                    <tr>
                        <th className="w-8 px-3 py-2" />
                        <th className="px-3 py-2 font-medium">Date</th>
                        <th className="px-3 py-2 font-medium">Particulars</th>
                        <th className="px-3 py-2 font-medium">Kind</th>
                        <th className="px-3 py-2 text-right font-medium">
                            Statutory
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            Charged
                        </th>
                        <th className="px-3 py-2 font-medium">Status</th>
                        <th className="px-3 py-2" />
                    </tr>
                </thead>
                <tbody>
                    {items.map((i) => {
                        const billed = i.bill_id !== null;
                        const isOpen = expanded === i.id;

                        return (
                            <>
                                <tr key={i.id} className="border-t">
                                    <td className="px-3 py-2">
                                        {!billed && i.is_billable && (
                                            <Checkbox
                                                checked={selected.has(i.id)}
                                                onCheckedChange={(c) =>
                                                    onToggle(i.id, c === true)
                                                }
                                                aria-label="Select for billing"
                                            />
                                        )}
                                    </td>
                                    <td className="px-3 py-2 whitespace-nowrap">
                                        {i.occurred_on}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div>{i.description}</div>
                                        {i.aro_item && (
                                            <button
                                                type="button"
                                                className="text-muted-foreground text-xs hover:underline"
                                                onClick={() =>
                                                    setExpanded(
                                                        isOpen ? null : i.id,
                                                    )
                                                }
                                            >
                                                <span className="font-mono">
                                                    {i.aro_item.code}
                                                </span>{' '}
                                                · {i.aro_item.rule_reference}{' '}
                                                {isOpen ? '▴' : '▾'}
                                            </button>
                                        )}
                                        {i.quantity !== null && (
                                            <div className="text-muted-foreground text-xs">
                                                {i.quantity} {i.unit ?? ''}
                                            </div>
                                        )}
                                        {i.uplift_justification && (
                                            <div className="text-xs italic">
                                                “{i.uplift_justification}”
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge variant="outline">
                                            {labelFor(i.kind)}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {i.computed_minimum_cents === null ? (
                                            '—'
                                        ) : (
                                            <span
                                                title={i.computed_bound ?? ''}
                                            >
                                                {i.computed_bound === 'maximum'
                                                    ? '≤ '
                                                    : i.computed_bound ===
                                                        'minimum'
                                                      ? '≥ '
                                                      : ''}
                                                {formatAmount(
                                                    i.computed_minimum_cents,
                                                )}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-right font-medium tabular-nums">
                                        {formatAmount(i.entered_cents)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {billed ? (
                                            <Link
                                                href={billShow(i.bill_id!)}
                                                className="text-xs underline"
                                            >
                                                Billed
                                            </Link>
                                        ) : i.shortfall_cents > 0 ? (
                                            <Badge variant="destructive">
                                                short{' '}
                                                {formatKes(i.shortfall_cents)}
                                            </Badge>
                                        ) : !i.is_billable ? (
                                            <Badge variant="outline">
                                                not billable
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                unbilled
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-right whitespace-nowrap">
                                        {canEdit && !billed && (
                                            <>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => onEdit(i)}
                                                >
                                                    Edit
                                                </Button>
                                                <Link
                                                    href={ChargeableItemController.destroy(
                                                        {
                                                            matter: matterId,
                                                            chargeableItem:
                                                                i.id,
                                                        },
                                                    )}
                                                    method="delete"
                                                    as="button"
                                                    className="text-muted-foreground px-2 text-xs hover:underline"
                                                    preserveScroll
                                                >
                                                    Remove
                                                </Link>
                                            </>
                                        )}
                                    </td>
                                </tr>
                                {isOpen && i.snapshot && (
                                    <tr
                                        key={`${i.id}-steps`}
                                        className="bg-muted/20 border-t"
                                    >
                                        <td colSpan={8} className="px-6 py-3">
                                            <ComputedSteps
                                                computed={i.snapshot}
                                            />
                                        </td>
                                    </tr>
                                )}
                            </>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
