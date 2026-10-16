import { useEffect, useState } from 'react';

export type Area = { latitude: number; longitude: number; radius: number };
export type AreaStatus = 'checking' | 'inside' | 'outside' | 'unknown';
export type Position = { latitude: number; longitude: number; accuracy: number };

/** Great-circle distance in metres, the same as the server (Geofence::distance). */
export function distance(
    a: { latitude: number; longitude: number },
    b: { latitude: number; longitude: number },
): number {
    const r = 6_371_000;
    const rad = (d: number) => (d * Math.PI) / 180;
    const dLat = rad(b.latitude - a.latitude);
    const dLng = rad(b.longitude - a.longitude);
    const h =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(rad(a.latitude)) * Math.cos(rad(b.latitude)) * Math.sin(dLng / 2) ** 2;

    return 2 * r * Math.asin(Math.min(1, Math.sqrt(h)));
}

/**
 * The phone's position and whether it's inside the branch area (handoff 25 status chip). The
 * server decides; this only tells the person before they tap. Accuracy counts in their favour
 * up to 100 m, as on the server.
 */
export function useLocation(
    area: Area | null,
    enabled: boolean,
): { status: AreaStatus; position: Position | null } {
    const [position, setPosition] = useState<Position | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (!enabled || typeof navigator === 'undefined' || !('geolocation' in navigator)) {
            return;
        }

        const id = navigator.geolocation.watchPosition(
            (p) => {
                setFailed(false);
                setPosition({
                    latitude: p.coords.latitude,
                    longitude: p.coords.longitude,
                    accuracy: Math.round(p.coords.accuracy),
                });
            },
            () => setFailed(true),
            { enableHighAccuracy: true, maximumAge: 30_000, timeout: 20_000 },
        );

        return () => navigator.geolocation.clearWatch(id);
    }, [enabled]);

    if (!area || !enabled) {
        return { status: 'unknown', position };
    }

    if (failed && !position) {
        return { status: 'unknown', position: null };
    }

    if (!position) {
        return { status: 'checking', position: null };
    }

    return {
        status:
            distance(area, position) <= area.radius + Math.min(position.accuracy, 100)
                ? 'inside'
                : 'outside',
        position,
    };
}
