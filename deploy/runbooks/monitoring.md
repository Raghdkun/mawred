# Monitoring and alerts

| Signal | Source | Goes to |
|---|---|---|
| Errors and slow requests | sentry-laravel → GlitchTip (D21) | GlitchTip email alerts |
| App up and ready | external uptime ping on `/health/ready` | the ping service's alerts |
| Backups ran | heartbeat after each offsite copy | the heartbeat service's alerts |
| Backups healthy | `backup:monitor` daily at 07:30 UTC | `OPS_ALERT_EMAILS` |
| Disk, memory, containers, reboot | `deploy/monitor.sh` every 5 min | `OPS_ALERT_EMAILS` |
| Deploys and drills | `deploy.sh`, `restore-drill.sh` | `OPS_ALERT_EMAILS` |
| Queues, slow queries, usage | Horizon (`/horizon`) and Pulse (`/pulse`) | look when needed |

## GlitchTip

1. After `deploy.sh init`, create the first user:
   `deploy/dc.sh exec glitchtip ./manage.py createsuperuser`. Sign-ups are off.
2. Sign in at `https://<GLITCHTIP_HOST>`, create the organisation and a **Laravel/PHP** project
   named `mawred`, and an alert rule that emails the team on new issues.
3. Copy the project's DSN and turn it into the internal form, so events never leave the server:
   `https://<key>@<GLITCHTIP_HOST>/<id>` → `SENTRY_LARAVEL_DSN=http://<key>@glitchtip:8000/<id>`.
4. `deploy/deploy.sh <current sha>` to pick it up, then
   `deploy/dc.sh exec app php artisan sentry:test` and check the event arrives.

What is sent: exception, stack trace, the route, the `request_id` tag (the same id as the
`X-Request-Id` header, logs and API `trace_id`), and 10 % of requests as traces. What is never
sent: request bodies, cookies, IP addresses, SQL bindings. Events are kept 30 days.

Browser-side errors are not collected yet: it needs `@sentry/react`, a new npm dependency, which
is a separate decision.

## Uptime ping

Use any external monitor (UptimeRobot, Better Stack…) checking `https://<APP_HOST>/health/ready`
every minute, expecting HTTP 200, alerting the IT contacts after 2 failures. `/health/ready`
answers `{"status":"ok|degraded|down","checks":{…}}`: `degraded` (still 200) means PDF rendering
is down; `down` (503) means the database, Redis or storage is.

## When an alert comes

- **Disk:** `docker system df`; `deploy/dc.sh exec app du -sh storage/backups storage/app`. Old
  images are pruned on deploy; `docker builder prune` is safe.
- **Memory:** `docker stats --no-stream`; compare with [sizing](../sizing.md).
- **A service is down:** `deploy/dc.sh ps`, `deploy/dc.sh logs --tail 200 <service>`, then
  `deploy/dc.sh up -d <service>`. Never restart `pgsql` during a deploy or backup.
- **Reboot needed:** pick a quiet hour, `sudo reboot`; everything starts by itself
  (`restart: unless-stopped`). Check the uptime ping recovers.
