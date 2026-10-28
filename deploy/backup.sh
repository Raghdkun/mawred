#!/usr/bin/env bash
# Nightly backup (ADR-0019, D15), run by mawred-backup.timer. spatie/laravel-backup dumps Postgres
# and the uploads into an AES-256 encrypted archive on the `backups` volume; this copies it to
# object storage with a write-only key, then pings the heartbeat so silence raises an alarm.
#
# Offsite layout (lifecycle rules expire each prefix; see deploy/runbooks/backup-and-restore.md):
#   daily/   every night        weekly/  Sundays
#   monthly/ the 1st            yearly/  1 January

# shellcheck source=deploy/lib.sh
source "$(dirname "$0")/lib.sh"

# Any failure, including a missing setting, alerts and tells the heartbeat.
finish() {
    local status=$?
    [ "$status" = 0 ] && return 0
    alert "Nightly backup failed" "deploy/backup.sh exited with $status. See: journalctl -u mawred-backup. Last night's archive may be missing offsite."
    heartbeat fail
}
trap finish EXIT

take_lock 3600
bucket="$(env_get BACKUP_S3_BUCKET)"
[ -n "$bucket" ] || die "BACKUP_S3_BUCKET is not set"
[ -n "$(env_get BACKUP_ARCHIVE_PASSWORD)" ] || die "BACKUP_ARCHIVE_PASSWORD is not set; archives would not be encrypted"

log "creating the archive"
dc exec -T app php artisan backup:run --no-interaction
dc exec -T app php artisan backup:clean --no-interaction

archive="$(dc exec -T app sh -c 'ls -1t storage/backups/mawred/*.zip | head -n 1')"
archive="$(basename "${archive//$'\r'/}")"
[ -n "$archive" ] || die "no archive found after backup:run"

offsite() {
    log "copying $archive to $1/"
    dc --profile ops run --rm --no-deps offsite copyto \
        "/backups/mawred/$archive" "offsite:$bucket/$1/$archive" \
        --no-check-dest --retries 5 --low-level-retries 10
}

offsite daily
[ "$(date -u +%u)" != 7 ] || offsite weekly
[ "$(date -u +%d)" != 01 ] || offsite monthly
[ "$(date -u +%m%d)" != 0101 ] || offsite yearly

heartbeat
log "backup $archive done"
