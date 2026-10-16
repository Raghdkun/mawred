import { Eraser } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

/**
 * A drawn signature (D83, handoff 32): pointer, pen and touch on a canvas, exported as a PNG data
 * URL. The pad is paper-white with dark ink in both themes, because the PNG is printed as-is on
 * the signed letter.
 */
export function SignaturePad({
    onChange,
    label,
    describedBy,
}: {
    onChange: (dataUrl: string | null) => void;
    label: string;
    describedBy?: string;
}) {
    const { t } = useTranslation('hiring');
    const canvas = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);
    const [empty, setEmpty] = useState(true);

    useEffect(() => {
        const node = canvas.current;

        if (!node) return;

        const ratio = window.devicePixelRatio || 1;
        node.width = node.offsetWidth * ratio;
        node.height = node.offsetHeight * ratio;
        const context = node.getContext('2d');
        context?.scale(ratio, ratio);
    }, []);

    const point = (event: React.PointerEvent<HTMLCanvasElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();

        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    };

    const context = () => {
        const ctx = canvas.current?.getContext('2d') ?? null;

        if (ctx) {
            ctx.lineWidth = 2.2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#182238';
        }

        return ctx;
    };

    const clear = () => {
        const node = canvas.current;
        node?.getContext('2d')?.clearRect(0, 0, node.width, node.height);
        setEmpty(true);
        onChange(null);
    };

    return (
        <div className="flex flex-col gap-2">
            <canvas
                ref={canvas}
                role="img"
                aria-label={label}
                aria-describedby={describedBy}
                className="h-40 w-full touch-none rounded-md border border-input bg-white"
                onPointerDown={(event) => {
                    event.currentTarget.setPointerCapture(event.pointerId);
                    drawing.current = true;
                    const ctx = context();
                    const { x, y } = point(event);
                    ctx?.beginPath();
                    ctx?.moveTo(x, y);
                }}
                onPointerMove={(event) => {
                    if (!drawing.current) return;

                    const ctx = context();
                    const { x, y } = point(event);
                    ctx?.lineTo(x, y);
                    ctx?.stroke();
                }}
                onPointerUp={() => {
                    if (!drawing.current) return;

                    drawing.current = false;
                    setEmpty(false);
                    onChange(canvas.current?.toDataURL('image/png') ?? null);
                }}
                data-signature-pad
            />
            <div className="flex items-center justify-between gap-2">
                <span className="text-caption text-text-secondary">
                    {empty ? t('signature.hint') : t('signature.done')}
                </span>
                <Button
                    type="button"
                    variant="ghost"
                    className="min-h-11"
                    onClick={clear}
                    disabled={empty}
                >
                    <Eraser aria-hidden />
                    {t('signature.clear')}
                </Button>
            </div>
        </div>
    );
}
