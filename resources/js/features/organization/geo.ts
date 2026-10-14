/** A circle as a GeoJSON ring (for the geofence overlay); radius in metres. */
export function circlePolygon(
    lat: number,
    lng: number,
    radiusM: number,
    steps = 64,
): [number, number][] {
    const earth = 6_371_008.8;
    const angular = radiusM / earth;
    const latRad = (lat * Math.PI) / 180;
    const lngRad = (lng * Math.PI) / 180;
    const ring: [number, number][] = [];

    for (let i = 0; i <= steps; i++) {
        const bearing = (i / steps) * 2 * Math.PI;
        const pointLat = Math.asin(
            Math.sin(latRad) * Math.cos(angular) +
                Math.cos(latRad) * Math.sin(angular) * Math.cos(bearing),
        );
        const pointLng =
            lngRad +
            Math.atan2(
                Math.sin(bearing) * Math.sin(angular) * Math.cos(latRad),
                Math.cos(angular) - Math.sin(latRad) * Math.sin(pointLat),
            );
        ring.push([(pointLng * 180) / Math.PI, (pointLat * 180) / Math.PI]);
    }

    return ring;
}

/**
 * Resolves a theme token (e.g. `--primary`, defined in OKLCH) to `rgb()` for libraries that
 * cannot read CSS variables or OKLCH, such as MapLibre paint properties.
 */
export function tokenColor(token: string, fallback = 'rgb(62, 92, 138)'): string {
    if (typeof document === 'undefined') {
        return fallback;
    }

    const value = getComputedStyle(document.documentElement).getPropertyValue(token).trim();
    const canvas = document.createElement('canvas');
    canvas.width = 1;
    canvas.height = 1;
    const context = canvas.getContext('2d');

    if (!value || !context) {
        return fallback;
    }

    context.fillStyle = value;
    context.fillRect(0, 0, 1, 1);
    const [r, g, b] = context.getImageData(0, 0, 1, 1).data;

    return `rgb(${r}, ${g}, ${b})`;
}
