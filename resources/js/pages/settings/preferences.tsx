import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Section } from '@/components/mw/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import { useAppearance, type Appearance } from '@/hooks/use-appearance';
import { namespacesForPage, prepareLocale } from '@/i18n';
import preferencesRoutes from '@/routes/preferences';

type Preferences = {
    locale: 'en' | 'ar';
    digits: 'latn' | 'arab';
    showHijri: boolean;
    timezone: string | null;
};

export default function PreferencesPage({
    preferences,
    timezones,
}: {
    preferences: Preferences;
    timezones: string[];
}) {
    const { t } = useTranslation('settings');
    const { appearance, updateAppearance } = useAppearance();
    const form = useForm<Preferences>(preferences);

    const submit = async (event: FormEvent) => {
        event.preventDefault();

        if (form.data.locale !== preferences.locale) {
            await prepareLocale(form.data.locale, namespacesForPage('settings/preferences'));
        }

        form.patch(preferencesRoutes.update.url(), {
            preserveScroll: true,
            onSuccess: () => {
                document.documentElement.dataset.digits = form.data.digits;
            },
        });
    };

    return (
        <>
            <Head title={t('preferences.pageTitle')} />
            <Section title={t('preferences.title')} description={t('preferences.description')}>
                <form onSubmit={submit} className="flex flex-col gap-6">
                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            {t('preferences.language')}
                        </legend>
                        <RadioGroup
                            value={form.data.locale}
                            onValueChange={(value) =>
                                form.setData('locale', value as Preferences['locale'])
                            }
                            className="flex flex-wrap gap-4"
                        >
                            {(['en', 'ar'] as const).map((value) => (
                                <Label key={value} className="flex items-center gap-2 font-normal">
                                    <RadioGroupItem value={value} />
                                    <span lang={value}>{t(`common:language.${value}`)}</span>
                                </Label>
                            ))}
                        </RadioGroup>
                    </fieldset>

                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            {t('preferences.digits.label')}
                        </legend>
                        <RadioGroup
                            value={form.data.digits}
                            onValueChange={(value) =>
                                form.setData('digits', value as Preferences['digits'])
                            }
                            className="flex flex-wrap gap-4"
                        >
                            {(['latn', 'arab'] as const).map((value) => (
                                <Label key={value} className="flex items-center gap-2 font-normal">
                                    <RadioGroupItem value={value} />
                                    {t(`preferences.digits.${value}`)}
                                </Label>
                            ))}
                        </RadioGroup>
                    </fieldset>

                    <div className="flex items-start justify-between gap-4">
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="showHijri">{t('preferences.hijri.label')}</Label>
                            <p className="text-small text-muted-foreground">
                                {t('preferences.hijri.description')}
                            </p>
                        </div>
                        <Switch
                            id="showHijri"
                            checked={form.data.showHijri}
                            onCheckedChange={(checked) => form.setData('showHijri', checked)}
                        />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="timezone">{t('preferences.timezone.label')}</Label>
                        <select
                            id="timezone"
                            dir="ltr"
                            value={form.data.timezone ?? ''}
                            onChange={(event) =>
                                form.setData(
                                    'timezone',
                                    event.target.value === '' ? null : event.target.value,
                                )
                            }
                            className="h-10 rounded-md border border-input bg-card px-3 text-sm text-foreground hover:border-input-hover focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 focus-visible:outline-none"
                        >
                            <option value="">{t('preferences.timezone.placeholder')}</option>
                            {timezones.map((zone) => (
                                <option key={zone} value={zone}>
                                    {zone}
                                </option>
                            ))}
                        </select>
                        <p className="text-caption text-muted-foreground">
                            {t('preferences.timezone.description')}
                        </p>
                        <InputError message={form.errors.timezone} />
                    </div>

                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            {t('preferences.appearance.label')}
                        </legend>
                        <RadioGroup
                            value={appearance}
                            onValueChange={(value) => updateAppearance(value as Appearance)}
                            className="flex flex-wrap gap-4"
                        >
                            {(['light', 'dark', 'system'] as const).map((value) => (
                                <Label key={value} className="flex items-center gap-2 font-normal">
                                    <RadioGroupItem value={value} />
                                    {t(`preferences.appearance.${value}`)}
                                </Label>
                            ))}
                        </RadioGroup>
                    </fieldset>

                    <Button type="submit" className="self-start" loading={form.processing}>
                        {t('preferences.save')}
                    </Button>
                </form>
            </Section>
        </>
    );
}
