import { describe, expect, it } from 'vitest';
import { circlePolygon } from './geo';

describe('circlePolygon', () => {
    it('draws a closed ring at the requested radius', () => {
        const ring = circlePolygon(24.7136, 46.6753, 150);
        expect(ring).toHaveLength(65);
        expect(ring[0]).toEqual(ring[64]);
        // North point: 150 m ≈ 0.001349° of latitude.
        expect(ring[0]![1] - 24.7136).toBeCloseTo(0.001349, 5);
    });
});
