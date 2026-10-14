import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AsyncPicker, type PickerOption } from '@/components/mw/async-picker';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader, Section } from '@/components/mw/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import approvals from '@/routes/approvals';
import delegations from '@/routes/delegations';

type Delegation = {
    id: string;
    to: string | null;
    startsOn: string;
    endsOn: string;
    reason: string | null;
    active: boolean;
};

/** "While I'm away" (ADR-0009): new approvals go to a colleague for a date range. */
export default function Delegations({
    delegations: list,
    today,
}: {
    delegations: Delegation[];
    today: string;
}) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const [to, setTo] = useState<PickerOption | null>(null);
    const form = useForm({ to: '', startsOn: today, endsOn: today, reason: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(delegations.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setTo(null);
            },
        });
    };

    return (
        <>
            <Head title={t('away.pageTitle')} />
            <Link
                href={approvals.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('inbox.back')}
            </Link>
            <PageHeader title={t('away.title')} description={t('away.description')} />

            <div className="flex max-w-3xl flex-col gap-6">
                <Section title={t('away.new')}>
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="flex flex-col gap-1.5">
                            <span className="text-sm font-medium">{t('delegate.to')}</span>
                            <AsyncPicker<{ id: string; name: string }>
                                endpoint="delegates"
                                queryKey="away-delegates"
                                toOption={(user) => ({ id: user.id, label: user.name })}
                                value={to}
                                onChange={(option) => {
                                    setTo(option);
                                    form.setData('to', option?.id ?? '');
                                }}
                                placeholder={t('delegate.search')}
                                label={t('delegate.to')}
                            />
                            <InputError message={form.errors.to} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="away-from">{t('away.from')}</Label>
                                <Input
                                    id="away-from"
                                    type="date"
                                    min={today}
                                    value={form.data.startsOn}
                                    onChange={(event) =>
                                        form.setData('startsOn', event.target.value)
                                    }
                                />
                                <InputError message={form.errors.startsOn} />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="away-to">{t('away.until')}</Label>
                                <Input
                                    id="away-to"
                                    type="date"
                                    min={form.data.startsOn}
                                    value={form.data.endsOn}
                                    onChange={(event) => form.setData('endsOn', event.target.value)}
                                />
                                <InputError message={form.errors.endsOn} />
                            </div>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="away-reason">{t('away.reason')}</Label>
                            <Input
                                id="away-reason"
                                maxLength={200}
                                value={form.data.reason}
                                onChange={(event) => form.setData('reason', event.target.value)}
                            />
                        </div>
                        <Button
                            type="submit"
                            className="self-start"
                            loading={form.processing}
                            disabled={!to}
                        >
                            {t('away.save')}
                        </Button>
                    </form>
                </Section>

                <Section title={t('away.current')}>
                    {list.length === 0 ? (
                        <p className="text-small text-muted-foreground">{t('away.none')}</p>
                    ) : (
                        <ul className="flex flex-col divide-y divide-border">
                            {list.map((delegation) => (
                                <li
                                    key={delegation.id}
                                    className="flex flex-wrap items-center gap-3 py-3"
                                >
                                    <span className="flex min-w-0 flex-1 flex-col">
                                        <span className="text-sm font-medium">{delegation.to}</span>
                                        <span className="text-caption text-muted-foreground">
                                            {formatDate(delegation.startsOn, locale, {
                                                dateStyle: 'medium',
                                            })}{' '}
                                            –{' '}
                                            {formatDate(delegation.endsOn, locale, {
                                                dateStyle: 'medium',
                                            })}
                                            {delegation.reason ? ` · ${delegation.reason}` : ''}
                                        </span>
                                    </span>
                                    {delegation.active ? (
                                        <Badge>{t('away.active')}</Badge>
                                    ) : (
                                        <Badge variant="outline">{t('away.upcoming')}</Badge>
                                    )}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(delegations.destroy.url(delegation.id), {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        {t('away.end')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
            </div>
        </>
    );
}
