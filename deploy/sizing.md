# Memory and disk budget (KVM 4: 4 vCPU, 16 GB, 200 GB NVMe)

Compose memory limits are ceilings, not typical use. Steady state is the sum of the limits; a
deploy briefly runs a second `app`; the restore drill adds a throwaway Postgres at 04:00 UTC.

| Service | Limit | Notes |
|---|---:|---|
| caddy | 128 MB | TLS, HTTP/3, proxy |
| app | 1.5 GB | FrankenPHP; ×2 for about a minute during a deploy |
| horizon | 1 GB | queue workers |
| scheduler | 256 MB | |
| ssr | 384 MB | careers pages |
| reverb | 256 MB | WebSockets |
| pgsql | 2 GB | Postgres 18 (Mawred and GlitchTip databases) |
| redis | 512 MB | `maxmemory 384mb`, AOF |
| gotenberg | 1 GB | Chromium PDF rendering, 2 at a time |
| waha | 512 MB | unofficial WhatsApp (GOWS engine) |
| clamav | 1.5 GB | signatures in memory (D20) |
| glitchtip | 1 GB | web and worker in one container |
| **Steady total** | **10 GB** | leaves ~5 GB for the OS, page cache and Postgres' file cache |
| Deploy peak | +1.5 GB | second `app` |
| Restore drill | +1.5 GB | throwaway Postgres, monthly |

A 4 GB swap file (harden.sh) absorbs short peaks; `monitor.sh` emails when free memory drops below
10 %. If that happens more than occasionally, see [scale-out](runbooks/scale-out.md) before
raising limits.

**Disk.** Docker logs are capped at 50 MB per container. Local backup archives are capped by
`BACKUP_LOCAL_MAX_MB` (20 GB). Old images are pruned after each deploy (current and previous kept).
`monitor.sh` emails at 80 % (`DISK_ALERT_PCT`).
