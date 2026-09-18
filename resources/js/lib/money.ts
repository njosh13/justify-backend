const kes = new Intl.NumberFormat('en-KE', {
    style: 'currency',
    currency: 'KES',
    currencyDisplay: 'code',
    minimumFractionDigits: 2,
});

const plain = new Intl.NumberFormat('en-KE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export function formatKes(cents: number | null | undefined): string {
    if (cents === null || cents === undefined) {
        return '—';
    }

    return kes
        .format(cents / 100)
        .replace('KES', 'KES ')
        .replace(/\s+/g, ' ');
}

export function formatAmount(cents: number | null | undefined): string {
    if (cents === null || cents === undefined) {
        return '—';
    }

    return plain.format(cents / 100);
}

/** Whole-shilling input value from cents, for form defaults. */
export function centsToInput(cents: number | null | undefined): string {
    if (cents === null || cents === undefined) {
        return '';
    }

    return (cents / 100).toString();
}

export function labelFor(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return value.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
}
