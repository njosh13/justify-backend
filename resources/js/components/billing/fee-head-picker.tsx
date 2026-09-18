import { useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { CatalogueItem } from '@/types/billing';

const scheduleNames: Record<number, string> = {
    1: 'Sales, purchases, securities',
    2: 'Leases',
    3: 'Companies',
    4: 'Trade marks',
    5: 'General business',
    6: 'High Court',
    7: 'Subordinate courts',
    8: 'Business premises tribunal',
    9: 'Rent restriction tribunal',
    10: 'Probate & administration',
    11: 'Other tribunals',
    12: 'Patents & designs',
};

export function scheduleName(n: number): string {
    return `Sch ${n} — ${scheduleNames[n] ?? ''}`;
}

export function FeeHeadPicker({
    catalogue,
    value,
    onChange,
    preferredSchedule,
}: {
    catalogue: CatalogueItem[];
    value: string | null;
    onChange: (item: CatalogueItem | null) => void;
    preferredSchedule?: number | null;
}) {
    const [schedule, setSchedule] = useState<string>(
        preferredSchedule ? String(preferredSchedule) : 'all',
    );
    const [query, setQuery] = useState('');

    const schedules = useMemo(
        () =>
            Array.from(new Set(catalogue.map((i) => i.schedule))).sort(
                (a, b) => a - b,
            ),
        [catalogue],
    );

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        return catalogue.filter((i) => {
            if (schedule !== 'all' && String(i.schedule) !== schedule) {
                return false;
            }
            if (q === '') {
                return true;
            }

            return (
                i.code.toLowerCase().includes(q) ||
                i.label.toLowerCase().includes(q) ||
                i.rule_reference.toLowerCase().includes(q)
            );
        });
    }, [catalogue, schedule, query]);

    const selected = catalogue.find((i) => i.id === value) ?? null;

    return (
        <div className="space-y-2">
            <div className="flex gap-2">
                <Select value={schedule} onValueChange={setSchedule}>
                    <SelectTrigger className="w-56">
                        <SelectValue placeholder="Schedule" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All schedules</SelectItem>
                        {schedules.map((s) => (
                            <SelectItem key={s} value={String(s)}>
                                {scheduleName(s)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search code, label or rule…"
                />
            </div>

            <div className="max-h-56 overflow-y-auto rounded-md border">
                {filtered.length === 0 && (
                    <div className="text-muted-foreground p-3 text-sm">
                        No heads match.
                    </div>
                )}
                {filtered.map((item) => (
                    <button
                        type="button"
                        key={item.id}
                        onClick={() => onChange(item)}
                        className={cn(
                            'hover:bg-accent flex w-full items-start gap-3 border-b px-3 py-2 text-left text-sm last:border-0',
                            selected?.id === item.id && 'bg-accent',
                            !item.is_active && 'opacity-50',
                        )}
                    >
                        <span className="text-muted-foreground w-44 shrink-0 font-mono text-xs">
                            {item.code}
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block truncate">{item.label}</span>
                            <span className="text-muted-foreground block text-xs">
                                {item.rule_reference}
                            </span>
                        </span>
                    </button>
                ))}
            </div>

            {selected && (
                <div className="text-muted-foreground text-xs">
                    Selected <span className="font-mono">{selected.code}</span>{' '}
                    · {selected.rule_reference}
                    {selected.note && <span> · {selected.note}</span>}
                    {selected.pointer_target && (
                        <span className="text-destructive">
                            {' '}
                            · Not chargeable itself: charged under{' '}
                            {selected.pointer_target}
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}
