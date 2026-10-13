# ADR-0020: Frontend data flow and library choices

- Status: Proposed
- Date: 2026-10-13

## Decision
- **Base**: the Laravel 13 React starter kit as shipped (Inertia v3, React 19.2 + React Compiler, Vite 8, Tailwind 4, shadcn new-york/Radix/lucide, Wayfinder, Fortify incl. 2FA and passkeys, SSR enabled).
- **Page data**: Inertia props, with `Inertia::defer`/`optional` for heavy panels and Inertia's infinite scroll for feeds.
- **Async widgets** (pickers, global search, roster lazy rows, notification center pages) call `/api/v1` with Sanctum stateful auth through **TanStack Query** using Inertia v3's fetch-based client conventions (no Axios). This is an addition to the brief's list, made to avoid writing a second set of JSON endpoints.
- **Forms**: react-hook-form 7 + zod 4 + @hookform/resolvers 5. Server 422 errors are mapped to fields by a shared `useServerErrors` hook.
- **Tables**: TanStack Table v9 (as in shadcn's current Data Table) + TanStack Virtual 3. `<DataView>` switches to cards below `md`.
- **DnD**: **@dnd-kit/core + @dnd-kit/sortable (classic, 6.x)** for the form builder, kanban and roster. It is stable and widely deployed, but has had no release since 2024-12. `@dnd-kit/react` (the new architecture) is still 0.x. All DnD code sits behind small in-repo wrappers (`features/*/dnd`) so it can be migrated later.
- **Canvas**: @xyflow/react 12.
- **Charts**: Recharts 3 via the shadcn `chart` component, using the `--chart-1..8` tokens.
- **Motion**: `motion` (v14). Presets live in `lib/motion.ts` and mirror the CSS tokens (a sync test checks this). Every preset respects reduced motion.
- **Handoff-driven additions**:
  - `vaul`: shadcn Drawer, bottom sheets with 50%/92% snap points.
  - `maplibre-gl`: OpenFreeMap tiles, geofence pin + radius (D33).
  - `react-pdf` (pdf.js): CV and offer viewer.
  - `signature_pad`: offer e-acceptance.
  - QR scanning via the native `BarcodeDetector` with an `@zxing/browser` fallback.
  - Recharts sparklines for KPI cards.
- **Other**: sonner, cmdk, date-fns 4 + @date-fns/tz, `libphonenumber-js` (E.164 parity with the server's libphonenumber), and `qrcode` for the kiosk.
- **PWA**: vite-plugin-pwa 2 (`injectManifest`). The custom service worker caches only the shell, assets and the offline page; Background Sync covers attendance and drafts; it handles push; and an "update available" toast drives the update.
- **A11y testing**: axe-core run directly in Vitest through a tiny helper (vitest-axe is stale), plus Pest browser `assertNoAccessibilityIssues()` on every journey.
