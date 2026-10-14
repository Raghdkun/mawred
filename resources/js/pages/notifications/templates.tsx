import { Head, Link, router, useForm } from '@inertiajs/react';
import { Bell, Mail, MessageCircle, RotateCcw } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import notifications from '@/routes/notifications';

type Locale = 'en' | 'ar';
type Text = { subject: string; body: string };
type Channel = { channel: 'in_app' | 'email'; customised: boolean; locales: Record<Locale, Text> };

type Props = {
    events: Array<{ key: string; category: string; customised: string[] }>;
    selected: string;
    variables: string[];
    channels: Channel[];
    samples: Record<Locale, Record<string, string>>;
};

const VARIABLE = /\{\{\s*([a-zA-Z_][a-zA-Z0-9_.]*)\s*(?:\|\s*[a-z_]+\s*)?\}\}/g;

/** Preview only: the server renders through SafeTemplate, which is the one that counts. */
function preview(template: string, values: Record<string, string>): string {
    return template.replace(VARIABLE, (_, name: string) => values[name] ?? '');
}

/**
 * Notification templates editor (handoff 42): pick an event, then edit its in-app and email
 * wording in English and Arabic side by side, with variable chips and a live preview.
 */
export default function NotificationTemplates({
    events,
    selected,
    variables,
    channels,
    samples,
}: Props) {
    const { t } = useTranslation('notifications');
    const [channelKey, setChannelKey] = useState<Channel['channel']>(
        channels[0]?.channel ?? 'email',
    );
    const channel = channels.find((candidate) => candidate.channel === channelKey) ?? channels[0];
    const categories = [...new Set(events.map((event) => event.category))];

    return (
        <>
            <Head title={t('templates.pageTitle')} />
            <PageHeader title={t('templates.title')} description={t('templates.description')} />

            <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                <nav
                    aria-label={t('templates.events')}
                    className="flex w-full flex-col gap-4 lg:w-72 lg:shrink-0"
                >
                    {categories.map((category) => (
                        <div key={category} className="flex flex-col gap-1">
                            <p className="px-3 text-overline text-muted-foreground uppercase">
                                {t(`preferences.categories.${category}.name`)}
                            </p>
                            {events
                                .filter((event) => event.category === category)
                                .map((event) => (
                                    <Link
                                        key={event.key}
                                        href={notifications.templates.index.url({
                                            query: { event: event.key },
                                        })}
                                        preserveScroll
                                        aria-current={event.key === selected ? 'page' : undefined}
                                        className={cn(
                                            'flex min-h-11 items-center justify-between gap-2 rounded-md px-3 text-sm text-text-secondary hover:bg-muted hover:text-foreground',
                                            event.key === selected &&
                                                'bg-sidebar-accent font-medium text-sidebar-accent-foreground',
                                        )}
                                    >
                                        {t(`templates.eventNames.${event.key}`)}
                                        {event.customised.length > 0 ? (
                                            <Badge variant="outline">
                                                {t('templates.customised')}
                                            </Badge>
                                        ) : null}
                                    </Link>
                                ))}
                        </div>
                    ))}
                </nav>

                <div className="flex min-w-0 flex-1 flex-col gap-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={channelKey}
                            onValueChange={(value) =>
                                value && setChannelKey(value as Channel['channel'])
                            }
                            aria-label={t('templates.channel')}
                        >
                            <ToggleGroupItem value="in_app" className="px-3">
                                <Bell aria-hidden /> {t('preferences.channels.in_app')}
                            </ToggleGroupItem>
                            <ToggleGroupItem value="email" className="px-3">
                                <Mail aria-hidden /> {t('preferences.channels.email')}
                            </ToggleGroupItem>
                        </ToggleGroup>
                        <span className="inline-flex items-center gap-1.5 text-caption text-muted-foreground">
                            <MessageCircle aria-hidden className="size-3.5" />
                            {t('templates.whatsappLater')}
                        </span>
                    </div>
                    {channel ? (
                        <Editor
                            key={`${selected}-${channel.channel}`}
                            event={selected}
                            channel={channel}
                            variables={variables}
                            samples={samples}
                        />
                    ) : null}
                </div>
            </div>
        </>
    );
}

function Editor({
    event,
    channel,
    variables,
    samples,
}: {
    event: string;
    channel: Channel;
    variables: string[];
    samples: Props['samples'];
}) {
    const { t } = useTranslation('notifications');
    const form = useForm({ locales: channel.locales });
    const [previewLocale, setPreviewLocale] = useState<Locale>('en');
    const [mobileLocale, setMobileLocale] = useState<Locale>('en');
    const focused = useRef<{
        locale: Locale;
        part: keyof Text;
        element: HTMLInputElement | HTMLTextAreaElement;
    } | null>(null);
    const hasSubject = channel.channel === 'email' || channel.locales.en.subject !== '';

    const insert = (variable: string) => {
        const target = focused.current ?? {
            locale: mobileLocale,
            part: 'body' as const,
            element: null,
        };
        const token = `{{ ${variable} }}`;
        const current = form.data.locales[target.locale][target.part];
        const start = target.element?.selectionStart ?? current.length;
        const end = target.element?.selectionEnd ?? current.length;
        const next = current.slice(0, start) + token + current.slice(end);

        form.setData('locales', {
            ...form.data.locales,
            [target.locale]: { ...form.data.locales[target.locale], [target.part]: next },
        });
        requestAnimationFrame(() => {
            target.element?.focus();
            target.element?.setSelectionRange(start + token.length, start + token.length);
        });
    };

    const field = (locale: Locale, part: keyof Text) => ({
        value: form.data.locales[locale][part],
        dir: locale === 'ar' ? ('rtl' as const) : ('ltr' as const),
        lang: locale,
        'aria-invalid':
            form.errors[`locales.${locale}.${part}` as keyof typeof form.errors] !== undefined ||
            form.errors[`${locale}.${part}` as keyof typeof form.errors] !== undefined,
        onFocus: (e: React.FocusEvent<HTMLInputElement | HTMLTextAreaElement>) =>
            (focused.current = { locale, part, element: e.currentTarget }),
        onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
            form.setData('locales', {
                ...form.data.locales,
                [locale]: { ...form.data.locales[locale], [part]: e.target.value },
            }),
    });
    const error = (locale: Locale, part: keyof Text) =>
        (form.errors as Record<string, string | undefined>)[`locales.${locale}.${part}`] ??
        (form.errors as Record<string, string | undefined>)[`${locale}.${part}`];
    const shown = {
        subject: preview(form.data.locales[previewLocale].subject, samples[previewLocale]),
        body: preview(form.data.locales[previewLocale].body, samples[previewLocale]),
    };

    return (
        <form
            className="flex flex-col gap-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.put(notifications.templates.update.url([event, channel.channel]), {
                    preserveScroll: true,
                });
            }}
        >
            <section className="rounded-lg bg-card p-5 shadow-sm">
                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <span className="text-small text-text-secondary">
                        {t('templates.variables')}
                    </span>
                    {variables.map((variable) => (
                        <button
                            key={variable}
                            type="button"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => insert(variable)}
                            className="inline-flex h-7 items-center rounded-full border border-border bg-muted px-2.5 font-mono text-caption text-foreground hover:border-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <bdi dir="ltr">{`{{ ${variable} }}`}</bdi>
                        </button>
                    ))}
                </div>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={mobileLocale}
                    onValueChange={(value) => value && setMobileLocale(value as Locale)}
                    aria-label={t('templates.language')}
                    className="mb-4 md:hidden"
                >
                    <ToggleGroupItem value="en">{t('templates.languages.en')}</ToggleGroupItem>
                    <ToggleGroupItem value="ar">{t('templates.languages.ar')}</ToggleGroupItem>
                </ToggleGroup>

                <div className="grid gap-6 md:grid-cols-2">
                    {(['en', 'ar'] as const).map((locale) => (
                        <fieldset
                            key={locale}
                            className={cn(
                                'flex flex-col gap-3',
                                locale !== mobileLocale && 'hidden md:flex',
                            )}
                        >
                            <legend className="mb-1 text-sm font-medium">
                                {t(`templates.languages.${locale}`)}
                            </legend>
                            {hasSubject ? (
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor={`${locale}-subject`}>
                                        {channel.channel === 'email'
                                            ? t('templates.subject')
                                            : t('templates.title_')}
                                    </Label>
                                    <Input
                                        id={`${locale}-subject`}
                                        maxLength={255}
                                        {...field(locale, 'subject')}
                                    />
                                    {error(locale, 'subject') ? (
                                        <p className="text-caption text-danger">
                                            {error(locale, 'subject')}
                                        </p>
                                    ) : null}
                                </div>
                            ) : null}
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor={`${locale}-body`}>{t('templates.body')}</Label>
                                <Textarea
                                    id={`${locale}-body`}
                                    rows={channel.channel === 'email' ? 8 : 3}
                                    maxLength={5000}
                                    {...field(locale, 'body')}
                                />
                                {error(locale, 'body') ? (
                                    <p className="text-caption text-danger">
                                        {error(locale, 'body')}
                                    </p>
                                ) : null}
                            </div>
                        </fieldset>
                    ))}
                </div>
            </section>

            <section
                aria-labelledby="template-preview"
                className="rounded-lg bg-card p-5 shadow-sm"
            >
                <div className="mb-4 flex items-center justify-between gap-2">
                    <h2 id="template-preview" className="text-h4">
                        {t('templates.preview')}
                    </h2>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        value={previewLocale}
                        onValueChange={(value) => value && setPreviewLocale(value as Locale)}
                        aria-label={t('templates.previewLanguage')}
                    >
                        <ToggleGroupItem value="en">{t('templates.short.en')}</ToggleGroupItem>
                        <ToggleGroupItem value="ar">{t('templates.short.ar')}</ToggleGroupItem>
                    </ToggleGroup>
                </div>
                <div
                    dir={previewLocale === 'ar' ? 'rtl' : 'ltr'}
                    lang={previewLocale}
                    className="rounded-md border border-border bg-background p-4"
                >
                    {channel.channel === 'email' ? (
                        <div className="mx-auto flex max-w-md flex-col gap-3 rounded-md bg-card p-5 shadow-sm">
                            <p className="text-h4">{shown.subject}</p>
                            <p className="text-small whitespace-pre-line text-foreground">
                                {shown.body}
                            </p>
                            <span className="inline-flex w-fit rounded-md bg-primary px-4 py-2 text-small font-medium text-primary-foreground">
                                {t(`templates.approveSample.${previewLocale}`)}
                            </span>
                        </div>
                    ) : (
                        <div className="flex max-w-md items-start gap-3 rounded-md bg-card p-3 shadow-sm">
                            <span
                                aria-hidden
                                className="mt-2 size-2 shrink-0 rounded-full bg-primary"
                            />
                            <div className="flex flex-col gap-0.5">
                                <p className="text-sm font-medium">{shown.subject}</p>
                                {shown.body ? (
                                    <p className="text-small text-text-secondary">{shown.body}</p>
                                ) : null}
                            </div>
                        </div>
                    )}
                </div>
            </section>

            <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={form.processing || !form.isDirty}>
                    {t('templates.save')}
                </Button>
                {channel.customised ? (
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() =>
                            router.delete(
                                notifications.templates.destroy.url([event, channel.channel]),
                                { preserveScroll: true },
                            )
                        }
                    >
                        <RotateCcw aria-hidden /> {t('templates.reset')}
                    </Button>
                ) : null}
            </div>
        </form>
    );
}
