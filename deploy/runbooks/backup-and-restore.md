# Backups, restores and drills

## What is backed up

Every night at 01:30 UTC `deploy/backup.sh` runs spatie/laravel-backup: a `pg_dump` of the
Mawred database plus everything under `storage/app` (uploads, generated PDFs, exports), zipped and
**AES-256 encrypted** with `BACKUP_ARCHIVE_PASSWORD`. The archive lands on the `backups` volume,
then goes to Hostinger Object Storage (D15) with a key that can only write.

Not in the archive, on purpose:
- `.env.production` (`APP_KEY`, `BLIND_INDEX_KEY`, the archive password…): kept in the password
  manager. **Without `APP_KEY` and `BLIND_INDEX_KEY`, encrypted fields in a restored database can't
  be read or searched.**
- The GlitchTip database (30 days of error events; disposable), Caddy's certificates (reissued),
  the WAHA session (re-pair), Redis (cache, queues and sessions; queued jobs are retried by their
  sources, people sign in again).

## Offsite layout and retention

| Prefix | Written | Lifecycle rule (set on the bucket) |
|---|---|---|
| `daily/` | every night | expire after 8 days |
| `weekly/` | Sundays | expire after 35 days |
| `monthly/` | the 1st | expire after 13 months |
| `yearly/` | 1 January | expire after 25 months |

This gives the ADR-0019 retention (7 daily, 4 weekly, 12 monthly, 2 yearly) without the server
ever being able to delete. If the bucket doesn't support lifecycle rules, delete expired archives
by hand with the admin key during the quarterly drill.

The local `backups` volume keeps 3 days of everything, then 7 daily and 2 weekly copies (spatie
cleanup), capped at `BACKUP_LOCAL_MAX_MB`.

## One-time setup

1. In Hostinger Object Storage, create a bucket (for example `mawred-backups`), turn on
   versioning and object lock if offered, and add the lifecycle rules above.
2. Create **two** keys:
   - *writer*: put-object only on that bucket → `BACKUP_S3_ACCESS_KEY_ID` / `…_SECRET_ACCESS_KEY`
     in `.env.production`, with `BACKUP_S3_ENDPOINT`, `BACKUP_S3_REGION`, `BACKUP_S3_BUCKET`;
   - *reader*: get and list only → the password manager, never the server.
3. Create a heartbeat check (healthchecks.io or similar) expecting a ping every 24 h with 2 h grace
   → `BACKUP_HEARTBEAT_URL`. It alerts when a night is missed for any reason.
4. Run it once by hand and check the archive appears in `daily/`:
   `sudo systemctl start mawred-backup && journalctl -u mawred-backup -f`.
5. Run a drill (below) the same day.

## Drills

- **Monthly, automatic** (`mawred-restore-drill.timer`, the 3rd at 04:00 UTC): restores the newest
  local archive into a throwaway Postgres, checks every migration is present with the deployed
  release, counts users, employees and files, and emails the result either way. Results are also
  appended to `/srv/mawred/.deploy/drills.log`.
- **Quarterly, by hand, from offsite**, so the bucket copy and the reader key are proven too:
  ```bash
  read -rs RESTORE_S3_ACCESS_KEY_ID; read -rs RESTORE_S3_SECRET_ACCESS_KEY
  export RESTORE_S3_ACCESS_KEY_ID RESTORE_S3_SECRET_ACCESS_KEY
  /srv/mawred/deploy/restore-drill.sh --offsite
  ```
  Then copy one archive to the second location named in the IT register (ADR-0019: a quarterly copy
  outside Hostinger), and note the date and result there.

A failed drill is an incident: find out why that day (wrong password, a damaged archive, a missing
migration) and run it again until it passes.

## Restoring production

Only for real data loss: it replaces the database with the archive's. Agree the restore point with
the business first; everything after it is lost.

1. `deploy/restore-drill.sh <archive>` (or `--offsite <archive>`) first, to prove the archive.
2. Stop writers: `deploy/dc.sh stop app horizon scheduler reverb ssr`.
3. Fetch and unpack the archive like the drill does (the script's steps are the reference), then
   load it into a fresh database:
   ```bash
   deploy/dc.sh exec pgsql psql -U mawred -d postgres -c 'ALTER DATABASE mawred RENAME TO mawred_broken_'"$(date +%F)"
   deploy/dc.sh exec pgsql createdb -U mawred mawred
   docker cp <unpacked>/db-dumps/postgresql-pgsql.sql "$(deploy/dc.sh ps -q pgsql)":/tmp/restore.sql
   deploy/dc.sh exec pgsql psql -v ON_ERROR_STOP=1 -U mawred -d mawred -f /tmp/restore.sql
   ```
4. Put the files back into the `storage` volume (the archive's `private/` and `public/` folders):
   `docker run --rm -v mawred_storage:/s -v <unpacked>:/r alpine sh -c 'cp -a /r/private /r/public /s/'`.
5. Make sure `.env.production` has the `APP_KEY` (and `APP_PREVIOUS_KEYS`) and `BLIND_INDEX_KEY`
   from the time of the archive.
6. `deploy/deploy.sh <current sha>` (runs any newer migrations), then check sign-in, a profile with
   documents, and a report.
7. Keep `mawred_broken_<date>` for a week, then drop it.
