# ADR-0014: PDF rendering through Gotenberg (Chromium)

- Status: Proposed
- Date: 2026-10-13

## Context
Arabic needs correct shaping, bidi, and mixed AR/EN runs. dompdf and mPDF either fail or need workarounds. Chromium renders exactly what the web UI renders.

## Decision
- A `gotenberg/gotenberg:8` container on the private network, with `CHROMIUM_MAX_CONCURRENCY=2` and `API_TIMEOUT=60s`, memory-capped at about 1 GB.
- A custom thin image (`docker/gotenberg/Dockerfile`) copies Readex Pro TTFs into `/usr/local/share/fonts`, so PDFs match the UI without sending fonts on each request. Noto Arabic is already in the base image as a fallback.
- `App\Support\Pdf\PdfRenderer` renders a Blade view (`resources/views/pdf/*`, `dir`/`lang` set, print CSS built from tokens), posts it with the official `gotenberg/gotenberg-php` client, and stores the result in medialibrary on the private disk.
- Rendering is always queued (`pdf` queue). The UI shows progress and Reverb sends a "ready" event.
- Documents: salary/experience certificates, offer letters, training certificates (with QR), attendance sheets and report PDF exports. Templates are bilingual Blade files. Admin-editable text blocks inside them go through `SafeTemplate` (ADR-0016).
- PDF snapshot tests: render the AR and EN fixtures in CI, using Gotenberg as a service container, and compare extracted text and page count. Visual diffs are a manual check.
