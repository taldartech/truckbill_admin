function dateOnlyString(value: string): string {
    if (value.includes('T')) {
        const date = new Date(value);
        const year = date.getUTCFullYear();
        const month = String(date.getUTCMonth() + 1).padStart(2, '0');
        const day = String(date.getUTCDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    return value.slice(0, 10);
}

export function todayDateString(): string {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }

    const dateOnly = dateOnlyString(value);
    const match = dateOnly.match(/^(\d{4})-(\d{2})-(\d{2})$/);

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

export function toDateInputValue(value?: string | null): string {
    if (!value) {
        return '';
    }

    return dateOnlyString(value);
}

export function followUpStatusFromDate(value?: string | null): string | null {
    if (!value) {
        return null;
    }

    const followUp = dateOnlyString(value);
    const today = todayDateString();

    if (followUp < today) {
        return 'overdue';
    }

    if (followUp === today) {
        return 'due_today';
    }

    return 'upcoming';
}

export function followUpLabel(status: string | null | undefined): string {
    switch (status) {
        case 'overdue':
            return 'Overdue';
        case 'due_today':
            return 'Due today';
        case 'upcoming':
            return 'Upcoming';
        default:
            return '';
    }
}
