# Go-live checklist

Tick each line on the day. Owners: IT (server, DNS, keys) and HR admin (settings, content).

## Domain and TLS (D126)
- [ ] Real domain chosen; `APP_HOST`, `CAREERS_HOST`, `GLITCHTIP_HOST`, `APP_URL`,
      `REVERB_BROWSER_HOST` and `MAIL_FROM_ADDRESS` set in `.env.production` (no `example.com` left).
- [ ] DNS A/AAAA records for all three hosts point at the server; `nginx-site.sh` run and
      certificates issued (open each); `sudo certbot renew --dry-run` passes.
- [ ] `http://<APP_HOST>` redirects to HTTPS; a WebSocket connects (a live notification arrives
      without a reload).
- [ ] `CAREERS_ON_OWN_HOST=true` if the careers site uses its own host (D11, D87).

## Server
- [ ] `harden.sh` run; root and password SSH logins refused; `ufw status` shows 22/80/443 only;
      `ss -ltn` shows the Mawred ports (8081/8082/8090/8070) on 127.0.0.1 only.
- [ ] A test deploy switches slots with no failed requests (`deploy.sh status` shows the other
      slot live afterwards).
- [ ] `.env.production` complete (`deploy/env.production.example`), `chmod 600`, copy in the
      password manager (with `APP_KEY`, `BLIND_INDEX_KEY`, `BACKUP_ARCHIVE_PASSWORD`).
- [ ] WAHA image tag pinned (D8); GlitchTip tag pinned.
- [ ] `deploy.sh init` done; `deploy.sh status` shows every service up.

## Email (D127)
- [ ] Postmark server created, sending domain verified (DKIM and Return-Path DNS records).
- [ ] SMTP/API credentials in `.env.production`; a test email from Mawred arrives.
- [ ] Bounce and spam-complaint webhook set in Postmark from **Integrations → Email** (secret URL
      shown once).
- [ ] DMARC record published for the domain (start with `p=none`, tighten later).

## Push and realtime (D128)
- [ ] `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` generated once and saved; push works on a phone.
- [ ] Reverb keys set; live updates arrive (a notification appears without reloading).

## Backups and monitoring
- [ ] Bucket, writer and reader keys, lifecycle rules ([backups](runbooks/backup-and-restore.md)).
- [ ] First backup ran; archive in `daily/`; heartbeat green.
- [ ] Offsite restore drill passed (`restore-drill.sh --offsite`).
- [ ] GlitchTip project, internal DSN, `sentry:test` received ([monitoring](runbooks/monitoring.md)).
- [ ] Uptime ping on `/health/ready` alerting IT.
- [ ] `OPS_ALERT_EMAILS` set; a test alert received
      (`deploy/dc.sh exec app php artisan ops:alert "Test" "Go-live check"`).

## Deploys (D125)
- [ ] GitHub `production` environment with reviewers and the four `DEPLOY_*` secrets.
- [ ] CI key installed with the forced command; a deploy from Actions succeeded.
- [ ] A rollback rehearsed (`deploy.sh rollback`, then deploy forward again).

## App
- [ ] `ProductionSeeder` run; owner created with `mawred:install`; setup wizard finished.
- [ ] Owner and HR admins enrolled in two-factor; demo data **not** seeded.
- [ ] Branches, holidays, policies and request types reviewed by HR.
- [ ] Integrations connected as needed (WhatsApp, Cognito) and green on **Integrations**.
- [ ] `phase` in `config/mawred.php` matches what is being launched.
