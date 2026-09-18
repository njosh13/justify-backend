import { Badge } from '@/components/ui/badge';
import { formatAmount, formatKes } from '@/lib/money';
import type { Computed } from '@/types/billing';

const boundCopy = {
    prescribed: {
        label: 'Prescribed',
        tone: 'default' as const,
        hint: 'The Order fixes this figure.',
    },
    minimum: {
        label: 'Not less than',
        tone: 'secondary' as const,
        hint: 'A floor. Charge more only with a recorded justification.',
    },
    maximum: {
        label: 'Not exceeding',
        tone: 'outline' as const,
        hint: 'A ceiling. Charge up to this, never above.',
    },
};

export function ComputedSteps({
    computed,
    compact = false,
}: {
    computed: Computed;
    compact?: boolean;
}) {
    const bound = boundCopy[computed.bound];

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <div className="text-muted-foreground text-xs tracking-wide uppercase">
                        {bound.label}
                    </div>
                    <div className="text-2xl font-semibold tabular-nums">
                        {formatKes(computed.amount_cents)}
                    </div>
                    {computed.ceiling_cents !== null && (
                        <div className="text-muted-foreground text-xs">
                            not to exceed {formatKes(computed.ceiling_cents)}
                        </div>
                    )}
                </div>
                <Badge variant={bound.tone} title={bound.hint}>
                    {bound.label}
                </Badge>
            </div>

            {!compact && (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-muted-foreground border-b text-left text-xs uppercase">
                            <th className="py-1 pr-2 font-medium">Rule</th>
                            <th className="py-1 pr-2 font-medium">Step</th>
                            <th className="py-1 text-right font-medium">KES</th>
                            <th className="py-1 pl-2 text-right font-medium">
                                Running
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {computed.steps.map((step, i) => (
                            <tr key={i} className="border-b last:border-0">
                                <td className="py-1 pr-2 align-top text-xs whitespace-nowrap">
                                    {step.rule_ref}
                                </td>
                                <td className="py-1 pr-2 align-top">
                                    {step.description}
                                </td>
                                <td className="py-1 text-right align-top tabular-nums">
                                    {step.replaces ? (
                                        <span className="text-muted-foreground">
                                            →
                                        </span>
                                    ) : (
                                        formatAmount(step.amount_cents)
                                    )}
                                </td>
                                <td className="py-1 pl-2 text-right align-top tabular-nums">
                                    {formatAmount(step.running_total_cents)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}

            <p className="text-muted-foreground text-xs leading-relaxed">
                {computed.provenance}
            </p>
        </div>
    );
}
