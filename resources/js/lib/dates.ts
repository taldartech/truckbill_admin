export function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }

    const match = value.slice(0, 10).match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) {
        return value;
    }

    const [, year, month, day] = match;

    return new Date(Number(year), Number(month) - 1, Number(day)).toLocaleDateString('en-GB');
}

export function formatDateTime(value?: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
