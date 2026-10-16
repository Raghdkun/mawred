/** Shift colours use the chart tokens 1–8 (tokens.css); full class names so Tailwind keeps them. */
const COLORS: Record<number, { block: string; swatch: string }> = {
    1: { block: 'border-chart-1 bg-chart-1/12', swatch: 'bg-chart-1' },
    2: { block: 'border-chart-2 bg-chart-2/12', swatch: 'bg-chart-2' },
    3: { block: 'border-chart-3 bg-chart-3/12', swatch: 'bg-chart-3' },
    4: { block: 'border-chart-4 bg-chart-4/12', swatch: 'bg-chart-4' },
    5: { block: 'border-chart-5 bg-chart-5/12', swatch: 'bg-chart-5' },
    6: { block: 'border-chart-6 bg-chart-6/12', swatch: 'bg-chart-6' },
    7: { block: 'border-chart-7 bg-chart-7/12', swatch: 'bg-chart-7' },
    8: { block: 'border-chart-8 bg-chart-8/12', swatch: 'bg-chart-8' },
};

export function shiftColor(color: number): { block: string; swatch: string } {
    return COLORS[color] ?? COLORS[1]!;
}

export const SHIFT_COLORS = [1, 2, 3, 4, 5, 6, 7, 8];

/** "07:00" → minutes. */
export function minutes(time: string): number {
    const [h, m] = time.split(':').map(Number);

    return (h ?? 0) * 60 + (m ?? 0);
}

export function isOvernight(startsAt: string, endsAt: string): boolean {
    return minutes(endsAt) <= minutes(startsAt);
}

/** ISO date (UTC-safe) helpers for calendar maths. */
export function parseDay(date: string): Date {
    const [y, m, d] = date.split('-').map(Number);

    return new Date(Date.UTC(y ?? 1970, (m ?? 1) - 1, d ?? 1));
}

export function isoDay(date: Date): string {
    return date.toISOString().slice(0, 10);
}

export function addDays(date: string, days: number): string {
    return isoDay(new Date(parseDay(date).getTime() + days * 86_400_000));
}

export function weekdayIso(date: string): number {
    const day = parseDay(date).getUTCDay();

    return day === 0 ? 7 : day;
}

export function daysBetween(from: string, to: string): string[] {
    const out: string[] = [];

    for (let day = from; day <= to; day = addDays(day, 1)) {
        out.push(day);
    }

    return out;
}
