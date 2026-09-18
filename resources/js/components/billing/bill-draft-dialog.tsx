import { useForm } from '@inertiajs/react';
import BillController from '@/actions/App/Http/Controllers/BillController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { CostBasis } from '@/types/billing';

export function BillDraftDialog({
    open,
    onOpenChange,
    matterId,
    itemIds,
    defaultBasis,
    contentious,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    matterId: string;
    itemIds: string[];
    defaultBasis: CostBasis;
    contentious: boolean;
}) {
    const form = useForm<{
        type: string;
        cost_basis: CostBasis;
        item_ids: string[];
    }>({
        type: contentious ? 'bill_of_costs' : 'fee_note',
        cost_basis: defaultBasis,
        item_ids: itemIds,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, item_ids: itemIds }));
        form.post(BillController.store.url(matterId), {
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Assemble a draft bill</DialogTitle>
                    <DialogDescription>
                        {itemIds.length} line{itemIds.length === 1 ? '' : 's'}{' '}
                        selected. Fee lines are recomputed on the chosen basis;
                        disbursements go to the foot.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="bd-type">Document</Label>
                        <select
                            id="bd-type"
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData('type', e.target.value)
                            }
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="fee_note">
                                Fee note / tax invoice (to the client)
                            </option>
                            <option value="bill_of_costs">
                                Bill of costs — para 69 five columns (for
                                taxation)
                            </option>
                        </select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="bd-basis">Cost basis</Label>
                        <select
                            id="bd-basis"
                            value={form.data.cost_basis}
                            onChange={(e) =>
                                form.setData(
                                    'cost_basis',
                                    e.target.value as CostBasis,
                                )
                            }
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="advocate_client">
                                Advocate and client (Part B, +50% on contentious
                                heads)
                            </option>
                            <option value="party_party">
                                Party and party (Part A, scale)
                            </option>
                        </select>
                        <p className="text-muted-foreground text-xs">
                            A party-and-party bill draws every fee line at the
                            scale figure regardless of what was entered.
                        </p>
                    </div>
                    <InputError message={form.errors.item_ids} />
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={form.processing || itemIds.length === 0}
                        >
                            Create draft
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
