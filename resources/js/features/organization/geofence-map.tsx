import type { GeoJSONSource, Map as MapLibreMap, Marker } from 'maplibre-gl';
import { MapPin } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';
import { circlePolygon, tokenColor } from './geo';
import 'maplibre-gl/dist/maplibre-gl.css';

export type Geofence = { lat: number; lng: number; radiusM: number };

/** OpenFreeMap vector tiles (D33): free, no key, OSM data. */
const STYLE_URL = 'https://tiles.openfreemap.org/styles/liberty';

/**
 * Branch geofence (screen 39, setup step 4): click or drag the pin to set the centre; the radius
 * comes from the slider next to it. Map controls stay LTR in Arabic (handoff RTL rules).
 * MapLibre loads on demand so other pages never pay for it.
 */
export function GeofenceMap({
    value,
    onChange,
    fallbackCenter,
    readOnly = false,
    className,
}: {
    value: Geofence | null;
    onChange?: (value: Geofence) => void;
    /** Where to look when no geofence is set yet (e.g. the country's capital). */
    fallbackCenter?: [number, number];
    readOnly?: boolean;
    className?: string;
}) {
    const { t } = useTranslation('organization');
    const container = useRef<HTMLDivElement>(null);
    const map = useRef<MapLibreMap | null>(null);
    const marker = useRef<Marker | null>(null);
    const latest = useRef({ value, onChange });
    const [ready, setReady] = useState(false);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        latest.current = { value, onChange };
    }, [value, onChange]);

    useEffect(() => {
        let cancelled = false;

        void Promise.all([
            import('maplibre-gl'),
            // MapLibre 6 ships its worker as an ES module with shared chunks; Vite bundles it.
            import('maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'),
        ])
            .then(([maplibre, worker]) => {
                maplibre.setWorkerUrl(worker.default);

                if (cancelled || !container.current) {
                    return;
                }

                const start = latest.current.value;
                const center: [number, number] = start
                    ? [start.lng, start.lat]
                    : [fallbackCenter?.[1] ?? 46.6753, fallbackCenter?.[0] ?? 24.7136];

                const instance = new maplibre.Map({
                    container: container.current,
                    style: STYLE_URL,
                    center,
                    zoom: start ? 15 : 10,
                    attributionControl: { compact: true },
                    cooperativeGestures: true,
                });
                instance.addControl(
                    new maplibre.NavigationControl({ showCompass: false }),
                    'top-left',
                );

                const accent = tokenColor('--primary');
                const pin = new maplibre.Marker({ draggable: !readOnly, color: accent });
                marker.current = pin;

                const place = (lng: number, lat: number) => {
                    const current = latest.current.value;
                    latest.current.onChange?.({
                        lat: Number(lat.toFixed(6)),
                        lng: Number(lng.toFixed(6)),
                        radiusM: current?.radiusM ?? 150,
                    });
                };

                if (!readOnly) {
                    instance.on('click', (event) => place(event.lngLat.lng, event.lngLat.lat));
                    pin.on('dragend', () => {
                        const position = pin.getLngLat();
                        place(position.lng, position.lat);
                    });
                }

                // `style.load` (not `load`): draw the pin as soon as the style is parsed, without
                // waiting for every tile, sprite and glyph.
                instance.once('style.load', () => {
                    instance.addSource('geofence', {
                        type: 'geojson',
                        data: { type: 'FeatureCollection', features: [] },
                    });
                    instance.addLayer({
                        id: 'geofence-fill',
                        type: 'fill',
                        source: 'geofence',
                        paint: { 'fill-color': accent, 'fill-opacity': 0.18 },
                    });
                    instance.addLayer({
                        id: 'geofence-line',
                        type: 'line',
                        source: 'geofence',
                        paint: { 'line-color': accent, 'line-width': 2 },
                    });
                    map.current = instance;
                    setReady(true);
                });
                instance.on('error', (event) => {
                    if (!map.current && event.error) {
                        setFailed(true);
                    }
                });
            })
            .catch(() => setFailed(true));

        return () => {
            cancelled = true;
            map.current?.remove();
            map.current = null;
        };
        // The map is created once; value changes are applied below.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        const instance = map.current;

        if (!ready || !instance) {
            return;
        }

        const source = instance.getSource('geofence') as GeoJSONSource | undefined;

        if (!value) {
            marker.current?.remove();
            source?.setData({ type: 'FeatureCollection', features: [] });

            return;
        }

        marker.current?.setLngLat([value.lng, value.lat]).addTo(instance);
        source?.setData({
            type: 'Feature',
            properties: {},
            geometry: {
                type: 'Polygon',
                coordinates: [circlePolygon(value.lat, value.lng, value.radiusM)],
            },
        });
    }, [ready, value]);

    return (
        <div
            dir="ltr"
            className={className ?? 'relative h-72 overflow-hidden rounded-lg border border-border'}
        >
            <div
                ref={container}
                role="application"
                aria-label={readOnly ? t('geofence.mapReadOnly') : t('geofence.mapLabel')}
                // MapLibre makes its container position: relative, so size it explicitly.
                className="size-full"
            />
            {!ready && !failed ? <Skeleton className="absolute inset-0 rounded-none" /> : null}
            {failed ? (
                <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-surface-sunken p-4 text-center text-small text-muted-foreground">
                    <MapPin aria-hidden className="size-5" />
                    {t('geofence.mapUnavailable')}
                </div>
            ) : null}
        </div>
    );
}
