import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

/**
 * Camera QR scanning for kiosk codes (D70). Uses qr-scanner, which prefers the browser's native
 * BarcodeDetector; loaded only when scanning starts.
 */
export function QrScan({ onCode }: { onCode: (code: string) => void }) {
    const { t } = useTranslation('attendance');
    const video = useRef<HTMLVideoElement>(null);
    const [error, setError] = useState(false);

    useEffect(() => {
        let stopped = false;
        let scanner: { stop: () => void; destroy: () => void } | null = null;

        void import('qr-scanner').then(async ({ default: QrScanner }) => {
            if (stopped || !video.current) return;

            const instance = new QrScanner(
                video.current,
                (result) => {
                    instance.stop();
                    onCode(result.data);
                },
                {
                    returnDetailedScanResult: true,
                    preferredCamera: 'environment',
                    highlightScanRegion: true,
                },
            );
            scanner = instance;

            try {
                await instance.start();
            } catch {
                setError(true);
            }
        });

        return () => {
            stopped = true;
            scanner?.stop();
            scanner?.destroy();
        };
    }, [onCode]);

    return (
        <div className="flex flex-col gap-2">
            {error ? (
                <p role="alert" className="text-small text-danger">
                    {t('clock.cameraError')}
                </p>
            ) : (
                <p className="text-small text-text-secondary">{t('clock.camera')}</p>
            )}
            <video
                ref={video}
                muted
                playsInline
                className="aspect-square w-full max-w-sm self-center rounded-lg bg-surface-sunken object-cover"
            />
        </div>
    );
}
