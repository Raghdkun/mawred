import { Head, Link, router, usePage } from '@inertiajs/react';
import { Archive, ChevronLeft, Eye, Rocket, Undo2, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualText, type Pair } from '@/components/mw/bilingual-text';
import { DirIcon } from '@/components/mw/dir-icon';
import { Field } from '@/components/mw/field';
import { PageHeader, Section } from '@/components/mw/page-header';
import { SwitchField } from '@/components/mw/switch-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { type Audience, AudienceEditor, EMPTY_AUDIENCE } from '@/features/training/audience-editor';
import { ModulesEditor } from '@/features/training/modules-editor';
import { SessionsEditor } from '@/features/training/sessions-editor';
import { CourseStatusBadge } from '@/features/training/status';
import { useLocale } from '@/hooks/use-locale';
import training from '@/routes/training';

type Editor = App.Domain.Training.Data.CourseEditorData;
type Props = {
    course: Editor | null;
    categories: string[];
    employmentTypes: App.Domain.Organization.Data.CatalogEntryData[];
    sessions: App.Domain.Training.Data.SessionData[];
    timezone: string;
};

const blank: Pair = { en: '', ar: '' };
const TABS = ['details', 'modules', 'sessions', 'audience'] as const;

/**
 * The course editor for trainers and HR (D98–D100, D103): details, modules and quizzes, and the
 * audience for a mandatory course; publishing puts it in the catalog.
 */
/** Keyed by course so creating one (same page component) starts the editor afresh. */
export default function CourseEditorPage(props: Props) {
    return <CourseEditor key={props.course?.id ?? 'new'} {...props} />;
}

function CourseEditor({ course, categories, employmentTypes, sessions, timezone }: Props) {
    const { t } = useTranslation('training');
    const { dir } = useLocale();
    const query = usePage().url.split('?')[1] ?? '';
    const requested = new URLSearchParams(query).get('tab');
    const initialTab = TABS.find((key) => key === requested) ?? 'details';
    const [tab, setTab] = useState<string>(course ? initialTab : 'details');
    const [title, setTitle] = useState<Pair>(course?.title ?? blank);
    const [summary, setSummary] = useState<Pair>(course?.summary ?? blank);
    const [description, setDescription] = useState<Pair>(course?.description ?? blank);
    const [category, setCategory] = useState(course?.category ?? 'skills');
    const [duration, setDuration] = useState(
        course?.durationMinutes ? String(course.durationMinutes) : '',
    );
    const [requiresApproval, setRequiresApproval] = useState(course?.requiresApproval ?? false);
    const [issuesCertificate, setIssuesCertificate] = useState(course?.issuesCertificate ?? true);
    const [validity, setValidity] = useState(
        course?.validityMonths ? String(course.validityMonths) : '',
    );
    const [mandatory, setMandatory] = useState(
        course?.audience !== null && course?.audience !== undefined,
    );
    const [audience, setAudience] = useState<Audience>(course?.audience ?? EMPTY_AUDIENCE);
    const [dueDays, setDueDays] = useState(course?.dueDays ? String(course.dueDays) : '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);
        const payload = {
            title,
            summary: summary.en ? summary : null,
            description: description.en ? description : null,
            category,
            durationMinutes: duration ? Number(duration) : null,
            requiresApproval,
            issuesCertificate,
            validityMonths: issuesCertificate && validity ? Number(validity) : null,
            audience: mandatory
                ? {
                      branches: audience.branches.map((v) => v.id),
                      departments: audience.departments.map((v) => v.id),
                      positions: audience.positions.map((v) => v.id),
                      employmentTypes: audience.employmentTypes.map((v) => v.id),
                      hiredWithinDays: audience.hiredWithinDays,
                  }
                : null,
            dueDays: mandatory && dueDays ? Number(dueDays) : null,
        };
        const options = {
            preserveScroll: true,
            onError: setErrors,
            onSuccess: () => setErrors({}),
            onFinish: () => setSaving(false),
        };

        if (course) {
            router.put(training.manage.courses.update.url(course.id), payload, options);
        } else {
            router.post(training.manage.courses.store.url(), payload, options);
        }
    };

    // The React Compiler reads a handler's dependencies during render: never dereference a
    // possibly-null prop inside one (`course!.id` crashed the new-course page).
    const courseId = course?.id ?? null;
    const status = (to: string) => {
        if (courseId) {
            router.put(
                training.manage.courses.status.url(courseId),
                { status: to },
                { preserveScroll: true, onError: setErrors },
            );
        }
    };

    const details = (
        <form
            className="flex flex-col gap-6"
            onSubmit={(event) => {
                event.preventDefault();
                save();
            }}
            data-course-details
        >
            <BilingualText
                id="title"
                label={t('editor.title')}
                value={title}
                onChange={setTitle}
                errors={errors}
                errorKey="title"
                required
                maxLength={160}
            />
            <BilingualText
                id="summary"
                label={t('editor.summary')}
                value={summary}
                onChange={setSummary}
                errors={errors}
                errorKey="summary"
                maxLength={300}
            />
            <BilingualText
                id="description"
                label={t('editor.description')}
                value={description}
                onChange={setDescription}
                errors={errors}
                errorKey="description"
                multiline
                maxLength={8000}
                hint={t('editor.plainTextHint')}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field id="category" label={t('editor.category')}>
                    <Select value={category} onValueChange={setCategory}>
                        <SelectTrigger id="category" className="min-h-11">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {categories.map((c) => (
                                <SelectItem key={c} value={c}>
                                    {t(`category.${c}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field id="duration" label={t('editor.duration')} error={errors.durationMinutes}>
                    <Input
                        id="duration"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        className="min-h-11"
                        value={duration}
                        onChange={(e) => setDuration(e.target.value)}
                    />
                </Field>
            </div>
            <SwitchField
                id="approval"
                label={t('editor.requiresApproval')}
                hint={t('editor.requiresApprovalHint')}
                checked={requiresApproval}
                onCheckedChange={setRequiresApproval}
            />
            <SwitchField
                id="certificate"
                label={t('editor.issuesCertificate')}
                hint={t('editor.issuesCertificateHint')}
                checked={issuesCertificate}
                onCheckedChange={setIssuesCertificate}
            />
            {issuesCertificate ? (
                <Field
                    id="validity"
                    label={t('editor.validity')}
                    hint={t('editor.validityHint')}
                    error={errors.validityMonths}
                    className="sm:max-w-xs"
                >
                    <Input
                        id="validity"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={120}
                        className="min-h-11"
                        value={validity}
                        onChange={(e) => setValidity(e.target.value)}
                    />
                </Field>
            ) : null}
            <Button type="submit" className="min-h-11 self-end" disabled={saving} data-save-course>
                {course ? t('editor.save') : t('editor.create')}
            </Button>
        </form>
    );

    return (
        <>
            <Head title={course ? course.title.en : t('editor.newTitle')} />
            <Link
                href={training.manage.courses.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('manage.title')}
            </Link>
            <PageHeader
                title={
                    course
                        ? dir === 'rtl' && course.title.ar
                            ? course.title.ar
                            : course.title.en
                        : t('editor.newTitle')
                }
                description={course ? undefined : t('editor.newHelp')}
                actions={
                    course ? (
                        <div className="flex flex-wrap items-center gap-2">
                            <CourseStatusBadge status={course.status} />
                            <Button asChild variant="outline" className="min-h-11">
                                <Link href={training.courses.show.url(course.id)}>
                                    <Eye aria-hidden />
                                    {t('editor.preview')}
                                </Link>
                            </Button>
                            <Button asChild variant="outline" className="min-h-11">
                                <Link href={training.manage.courses.people.url(course.id)}>
                                    <Users aria-hidden />
                                    {t('course.people')}
                                </Link>
                            </Button>
                            {course.status !== 'published' ? (
                                <Button
                                    className="min-h-11"
                                    onClick={() => status('published')}
                                    data-publish
                                >
                                    <Rocket aria-hidden />
                                    {t('editor.publish')}
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() => status('archived')}
                                    data-archive
                                >
                                    <Archive aria-hidden />
                                    {t('editor.archive')}
                                </Button>
                            )}
                            {course.status === 'archived' ? (
                                <Button
                                    variant="ghost"
                                    className="min-h-11"
                                    onClick={() => status('draft')}
                                >
                                    <Undo2 aria-hidden />
                                    {t('editor.toDraft')}
                                </Button>
                            ) : null}
                        </div>
                    ) : null
                }
            />
            {errors.error ? (
                <p role="alert" className="mb-4 rounded-md bg-danger-bg p-3 text-small text-danger">
                    {errors.error}
                </p>
            ) : null}
            {course ? (
                <Tabs value={tab} onValueChange={setTab} dir={dir}>
                    <TabsList className="mb-6">
                        {TABS.map((key) => (
                            <TabsTrigger
                                key={key}
                                value={key}
                                className="min-h-11 text-foreground"
                                data-tab={key}
                            >
                                {t(`editor.tabs.${key}`)}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                    <TabsContent value="details" className="max-w-3xl">
                        {details}
                    </TabsContent>
                    <TabsContent value="modules" className="max-w-3xl">
                        <ModulesEditor courseId={course.id} modules={course.modules} />
                    </TabsContent>
                    <TabsContent value="sessions" className="max-w-3xl">
                        <SessionsEditor
                            courseId={course.id}
                            sessions={sessions}
                            timezone={timezone}
                        />
                    </TabsContent>
                    <TabsContent value="audience" className="max-w-3xl">
                        <Section title={t('audience.title')} description={t('audience.help')}>
                            <div className="flex flex-col gap-5">
                                <AudienceEditor
                                    mandatory={mandatory}
                                    onMandatoryChange={setMandatory}
                                    audience={audience}
                                    onAudienceChange={setAudience}
                                    dueDays={dueDays}
                                    onDueDaysChange={setDueDays}
                                    employmentTypes={employmentTypes}
                                    errors={errors}
                                />
                                <Button
                                    className="min-h-11 self-end"
                                    onClick={save}
                                    disabled={saving}
                                    data-save-audience
                                >
                                    {t('audience.save')}
                                </Button>
                            </div>
                        </Section>
                    </TabsContent>
                </Tabs>
            ) : (
                <div className="max-w-3xl">{details}</div>
            )}
        </>
    );
}
