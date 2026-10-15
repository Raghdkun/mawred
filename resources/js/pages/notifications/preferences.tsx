import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, Lock, MessageCircle } from 'lucide-react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader, Section } from '@/components/mw/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import notifications from '@/routes/notifications';

type Row = {
    category: string;
    critical: boolean;
    channels: Record<string, { enabled: boolean; locked: boolean }>;
};

type WhatsApp = {
    available: boolean;
    phone: string | null;
    verified: boolean;
    pending: boolean;
    suggested: string | null;
};

type Props = {
    whatsapp: WhatsApp;
    matrix: Row[];
    quietHours: { start: string | null; end: string | null };
    digest: boolean;
    timezone: string;
};

const CHANNELS = ['in_app', 'email', 'whatsapp'] as const;

/**
 * Notification preferences (handoff 44): category × channel switches, quiet hours in the
 * person's own timezone and the daily digest. Critical in-app messages stay on.
 */
export default function NotificationPreferences({
    matrix,
    quietHours,
    digest,
    timezone,
    whatsapp,
}: Props) {
    const { t } = useTranslation('notifications');
    const form = useForm({
        channels: Object.fromEntries(
            matrix.map((row) => [
                row.category,
                Object.fromEntries(
                    Object.entries(row.channels).map(([channel, cell]) => [channel, cell.enabled]),
                ),
            ]),
        ) as Record<string, Record<string, boolean>>,
        quietHoursStart: quietHours.start ?? '',
        quietHoursEnd: quietHours.end ?? '',
        digest,
    });
    const quiet = form.data.quietHoursStart !== '' || form.data.quietHoursEnd !== '';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            quietHoursStart: data.quietHoursStart || null,
            quietHoursEnd: data.quietHoursEnd || null,
        }));
        form.put(notifications.preferences.update.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('preferences.pageTitle')} />
            <Link
                href={notifications.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('title')}
            </Link>
            <PageHeader title={t('preferences.title')} description={t('preferences.description')} />

            <div className="mb-8 max-w-3xl">
                <WhatsAppCard whatsapp={whatsapp} />
            </div>

            <form onSubmit={submit} className="flex max-w-3xl flex-col gap-8">
                <Section title={t('preferences.channelsTitle')}>
                    <div className="overflow-x-auto rounded-lg border border-border bg-card">
                        <table className="w-full text-sm">
                            <caption className="sr-only">{t('preferences.channelsTitle')}</caption>
                            <thead className="bg-muted/60 text-start text-caption text-text-secondary">
                                <tr>
                                    <th scope="col" className="px-4 py-3 text-start font-medium">
                                        {t('preferences.category')}
                                    </th>
                                    {CHANNELS.map((channel) => (
                                        <th
                                            key={channel}
                                            scope="col"
                                            className="w-20 px-2 py-3 text-center font-medium sm:w-28 sm:px-4"
                                        >
                                            {t(`preferences.channels.${channel}`)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {matrix.map((row) => (
                                    <tr key={row.category} className="border-t border-border">
                                        <th
                                            scope="row"
                                            className="px-4 py-3 text-start font-normal"
                                        >
                                            <span className="block font-medium text-foreground">
                                                {t(`preferences.categories.${row.category}.name`)}
                                            </span>
                                            <span className="hidden text-caption text-muted-foreground sm:block">
                                                {t(`preferences.categories.${row.category}.help`)}
                                            </span>
                                        </th>
                                        {CHANNELS.map((channel) => {
                                            const cell = row.channels[channel];
                                            const label = `${t(`preferences.categories.${row.category}.name`)} · ${t(`preferences.channels.${channel}`)}`;

                                            return (
                                                <td
                                                    key={channel}
                                                    className="px-2 py-3 text-center sm:px-4"
                                                >
                                                    {cell ? (
                                                        <span className="inline-flex items-center gap-1.5">
                                                            <Switch
                                                                checked={
                                                                    cell.locked
                                                                        ? true
                                                                        : (form.data.channels[
                                                                              row.category
                                                                          ]?.[channel] ?? true)
                                                                }
                                                                disabled={cell.locked}
                                                                aria-label={label}
                                                                aria-describedby={
                                                                    cell.locked
                                                                        ? 'locked-help'
                                                                        : undefined
                                                                }
                                                                onCheckedChange={(value) =>
                                                                    form.setData('channels', {
                                                                        ...form.data.channels,
                                                                        [row.category]: {
                                                                            ...form.data.channels[
                                                                                row.category
                                                                            ],
                                                                            [channel]: value,
                                                                        },
                                                                    })
                                                                }
                                                            />
                                                            {cell.locked ? (
                                                                <Lock
                                                                    aria-hidden
                                                                    className="size-3.5 text-muted-foreground"
                                                                />
                                                            ) : null}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p
                        id="locked-help"
                        className="mt-2 flex items-center gap-1.5 text-caption text-muted-foreground"
                    >
                        <Lock aria-hidden className="size-3.5" />
                        {t('preferences.locked')}
                    </p>
                </Section>

                <Section
                    title={t('preferences.quietTitle')}
                    description={t('preferences.quietHelp', { timezone })}
                >
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="quiet-start">{t('preferences.from')}</Label>
                            <Input
                                id="quiet-start"
                                type="time"
                                className="w-36"
                                value={form.data.quietHoursStart}
                                aria-invalid={form.errors.quietHoursStart !== undefined}
                                onChange={(event) =>
                                    form.setData('quietHoursStart', event.target.value)
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="quiet-end">{t('preferences.to')}</Label>
                            <Input
                                id="quiet-end"
                                type="time"
                                className="w-36"
                                value={form.data.quietHoursEnd}
                                aria-invalid={form.errors.quietHoursEnd !== undefined}
                                onChange={(event) =>
                                    form.setData('quietHoursEnd', event.target.value)
                                }
                            />
                        </div>
                        {quiet ? (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    form.setData((data) => ({
                                        ...data,
                                        quietHoursStart: '',
                                        quietHoursEnd: '',
                                    }))
                                }
                            >
                                {t('preferences.clearQuiet')}
                            </Button>
                        ) : null}
                    </div>
                    <InputError
                        message={form.errors.quietHoursStart ?? form.errors.quietHoursEnd}
                    />
                </Section>

                <Section title={t('preferences.digestTitle')}>
                    <div className="flex items-start gap-3">
                        <Switch
                            id="digest"
                            checked={form.data.digest}
                            onCheckedChange={(value) => form.setData('digest', value)}
                        />
                        <div className="flex flex-col gap-0.5">
                            <Label htmlFor="digest">{t('preferences.digest')}</Label>
                            <p className="text-small text-text-secondary">
                                {t('preferences.digestHelp')}
                            </p>
                        </div>
                    </div>
                </Section>

                <div>
                    <Button type="submit" disabled={form.processing}>
                        {t('preferences.save')}
                    </Button>
                </div>
            </form>
        </>
    );
}

/**
 * WhatsApp opt-in (D58): add a number, confirm the code we send over WhatsApp, or remove it.
 * Nothing goes to WhatsApp until the number is confirmed.
 */
function WhatsAppCard({ whatsapp }: { whatsapp: WhatsApp }) {
    const { t } = useTranslation('notifications');
    const start = useForm({ phone: whatsapp.phone ?? whatsapp.suggested ?? '' });
    const confirm = useForm({ code: '' });

    return (
        <Section
            title={t('whatsapp.title')}
            description={whatsapp.available ? t('whatsapp.help') : t('whatsapp.unavailable')}
        >
            {!whatsapp.available ? null : whatsapp.verified ? (
                <div className="flex flex-wrap items-center gap-3">
                    <MessageCircle aria-hidden className="size-5 text-success" />
                    <span className="flex-1 text-sm">
                        {t('whatsapp.connected')} <bdi dir="ltr">{whatsapp.phone}</bdi>
                    </span>
                    <Button
                        variant="ghost"
                        onClick={() =>
                            router.delete(notifications.whatsapp.remove.url(), {
                                preserveScroll: true,
                            })
                        }
                    >
                        {t('whatsapp.remove')}
                    </Button>
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    <form
                        className="flex flex-wrap items-end gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            start.post(notifications.whatsapp.start.url(), {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="whatsapp-phone">{t('whatsapp.phone')}</Label>
                            <Input
                                id="whatsapp-phone"
                                type="tel"
                                dir="ltr"
                                autoComplete="tel"
                                className="w-56"
                                value={start.data.phone}
                                aria-invalid={start.errors.phone !== undefined}
                                onChange={(event) => start.setData('phone', event.target.value)}
                            />
                        </div>
                        <Button
                            type="submit"
                            variant={whatsapp.pending ? 'outline' : 'default'}
                            loading={start.processing}
                        >
                            {whatsapp.pending ? t('whatsapp.resend') : t('whatsapp.send')}
                        </Button>
                    </form>
                    <InputError
                        message={
                            start.errors.phone ??
                            (start.errors as Record<string, string | undefined>).error
                        }
                    />
                    {whatsapp.pending ? (
                        <form
                            className="flex flex-wrap items-end gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                confirm.post(notifications.whatsapp.confirm.url(), {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="whatsapp-code">{t('whatsapp.code')}</Label>
                                <Input
                                    id="whatsapp-code"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    dir="ltr"
                                    maxLength={6}
                                    className="w-32 tracking-widest"
                                    value={confirm.data.code}
                                    aria-invalid={confirm.errors.code !== undefined}
                                    onChange={(event) =>
                                        confirm.setData('code', event.target.value)
                                    }
                                />
                            </div>
                            <Button type="submit" loading={confirm.processing}>
                                {t('whatsapp.confirm')}
                            </Button>
                            <InputError message={confirm.errors.code} />
                        </form>
                    ) : null}
                </div>
            )}
        </Section>
    );
}
