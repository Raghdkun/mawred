# Mawred design folder

- `canvas-source/` — every artboard of the Mawred canvas (`*.dc.html`) plus `canvas.json` (layout). Images inside reference the canvas's hosted assets (`/_blob/...`); the same images are in the folders below.
- `brand/logo/` — symbol, wordmarks (EN/AR) and lockups in five variants: color, mono-blue, white-on-blue, dark, currentColor.
- `brand/app-assets/` — favicon, Apple touch, PWA (any + maskable), 1024 app icon, splash screens, email / WhatsApp / Open Graph headers (EN + AR), as SVG and PNG. `manifest.webmanifest` for the PWA.
- `icons/` — 20 custom module icons (24 px grid, 1.75 stroke, currentColor).
- `tokens/mawred-tokens.css` — Tailwind v4 `@theme` + shadcn/ui variables, OKLCH, light + dark.
- `illustrations/current/` — the current illustration set (scenes, empty states, system pages, success moments, avatars, certificate frame, people lineup), light and dark, SVG and @2x PNG.
- `illustrations/previous-version/` — the first versions of the three illustrations that were later changed (login hero, onboarding slide 2, people lineup), light and dark.
