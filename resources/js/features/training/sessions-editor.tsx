import { Link, router } from '@inertiajs/react';
import { Plus, QrCode, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import training from '@/routes/training';
import { SessionWhen } from './session-card';
import { SessionDialog } from './session-dialog';

type Session = App.Domain.Training.Data.SessionData;

/** A course's sessions for trainers (D98, D107): plan, change, cancel, and open the roster or QR. */
export function SessionsEditor({
    courseId,
    sessions,
    timezone,
}: {
    courseId: string;
    sessions: Session[];
    timezone: string;
}) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const [editing, setEditing] = useState<Session | 'new' | null>(null);
    const n = (value: number) => formatNumber(value, locale);

    return (
        <div className="flex flex-col gap-4" data-sessions-editor>
            <p className="text-small text-text-secondary">{t('sessions.editorHelp')}</p>
            {sessions.length === 0 ? (
                <p className="text-small text-text-secondary">{t('sessions.none')}</p>
            ) : null}
            <ul className="flex flex-col gap-3">
                {sessions.map((session) => (
                    <li
                        key={session.id}
                        className="flex flex-col gap-2 rounded-lg border border-border bg-card p-4"
                        data-session-row={session.id}
                    >
                        <span className="text-small font-medium">
                            <SessionWhen session={session} />
                        </span>
                        <span className="text-caption text-text-secondary">
                            {session.status === 'cancelled'
                                ? t('sessions.cancelled')
                                : [
                                      session.mode === 'online'
                                          ? t('sessions.online')
                                          : (session.location ?? t('sessions.onsite')),
                                      session.capacity === null
                                          ? t('sessions.bookedOpen', { booked: n(session.booked) })
                                          : t('sessions.bookedOf', {
                                                booked: n(session.booked),
                                                capacity: n(session.capacity),
                                            }),
                                      session.waitlisted > 0
                                          ? t('sessions.waiting', { count: session.waitlisted })
                                          : null,
                                  ]
                                      .filter(Boolean)
                                      .join(' · ')}
                        </span>
                        {session.status === 'scheduled' ? (
                            <div className="flex flex-wrap gap-2">
                                <Button asChild variant="outline" className="min-h-11">
                                    <Link href={training.manage.sessions.show.url(session.id)}>
                                        <Users aria-hidden />
                                        {t('sessions.roster')}
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" className="min-h-11">
                                    <Link href={training.manage.sessions.display.url(session.id)}>
                                        <QrCode aria-hidden />
                                        {t('roster.showQr')}
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    className="min-h-11"
                                    onClick={() => setEditing(session)}
                                >
                                    {t('sessions.edit')}
                                </Button>
                                <Button
                                    variant="ghost"
                                    className="min-h-11 text-danger"
                                    onClick={() =>
                                        router.delete(
                                            training.manage.sessions.cancel.url(session.id),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('sessions.cancel')}
                                </Button>
                            </div>
                        ) : null}
                    </li>
                ))}
            </ul>
            <Button
                variant="outline"
                className="min-h-11 self-start"
                onClick={() => setEditing('new')}
                data-new-session
            >
                <Plus aria-hidden />
                {t('sessions.new')}
            </Button>
            {editing ? (
                <SessionDialog
                    courseId={courseId}
                    session={editing === 'new' ? null : editing}
                    timezone={timezone}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </div>
    );
}
