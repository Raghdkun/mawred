# ADR-0017: PHP runtime = FrankenPHP (classic mode); edge proxy = Caddy

- Status: Proposed
- Date: 2026-10-13

## Context
The brief asks for PHP-FPM + Nginx or FrankenPHP, with a justification. As of 2026-10, FrankenPHP 1.13 is mature, Octane officially supports it, the Laravel 13 docs cover it, and Laravel Cloud's Octane runtime uses it.

## Decision
- The **app** image is built on `dunglas/frankenphp` (PHP 8.4) and runs in **classic mode** (`php_server`, no worker mode) for v1.
  - One process instead of nginx + php-fpm + supervisor, with HTTP/2 and early hints.
  - The same image runs `horizon`, `schedule:work`, `reverb:start` and (optionally) `inertia:start-ssr` as separate Compose services.
  - Classic mode has none of the worker-mode state-leak risks, so the brief's `preventLazyLoading` and strict-mode development flow is unaffected.
- **Octane worker mode** is a Phase 9 option, gated on an audit for static state and singletons and on measured need. The budgets should be met without it.
- The **edge** is a separate **Caddy 2** container: automatic Let's Encrypt, WebSocket proxying of `/app` and `/apps` to Reverb, security headers, compression and request size limits.
  - Upstreams use `dynamic a` (Docker DNS) with active health checks, so a deploy can run two `app` containers side by side for zero-downtime rollout (ADR-0019).
  - FrankenPHP's own TLS stays off because Caddy terminates TLS.
- **Hostnames (D11)**: `hr.<domain>` serves the app, `/api/v1` and Reverb websockets. `careers.<domain>` serves the public careers site, booking pages and the certificate verification page, under a separate CSP. Session cookies are scoped to `hr.` only. Inertia SSR runs as an `ssr` service (same image + Node) and is required for careers SEO; whether SSR is enabled on the internal app is decided by measurement in Phase 6.

## Alternatives
- PHP-FPM + Nginx: the most familiar option, but two processes per container and more config. A perfectly valid fallback; the Dockerfile is kept simple enough to switch.
- Traefik: equally capable, label-driven, and docker-rollout's docs assume it. Caddy is simpler for one host.
