#!/usr/bin/env bash
# Restore drill (ADR-0019): proves a backup restores. It decrypts an archive, loads the dump into a
# throwaway Postgres, runs the deployed release's migration check against it and counts what came
# back. Production is never touched. mawred-restore-drill.timer runs it monthly on the newest local
# archive; the quarterly drill pulls from object storage with the read key (runbook).
#
#   restore-drill.sh [archive.zip]             newest (or named) archive on the backups volume
#   restore-drill.sh --offsite [archive.zip]   from daily/ in the bucket; needs RESTORE_S3_ACCESS_KEY_ID
#                                              and RESTORE_S3_SECRET_ACCESS_KEY in the environment

# shellcheck source=deploy/lib.sh
source "$(dirname "$0")/lib.sh"

from_offsite=no
if [ "${1:-}" = --offsite ]; then
    from_offsite=yes
    shift
fi
archive="${1:-}"
run_id="mawred-drill-$$"
started=$SECONDS

# Always removes the throwaway database; any failure (including a pending migration) alerts.
finish() {
    local status=$?
    docker rm -f "$run_id-pg" >/dev/null 2>&1 || true
    docker network rm "$run_id" >/dev/null 2>&1 || true
    docker volume rm "$run_id" >/dev/null 2>&1 || true
    [ "$status" = 0 ] && return 0
    alert "Restore drill failed" "deploy/restore-drill.sh exited with $status. A backup may not restore: check it today (deploy/runbooks/backup-and-restore.md)."
}
trap finish EXIT

take_lock 3600
docker volume create "$run_id" >/dev/null
docker network create "$run_id" >/dev/null

if [ "$from_offsite" = yes ]; then
    : "${RESTORE_S3_ACCESS_KEY_ID:?export the read-only restore key first}" "${RESTORE_S3_SECRET_ACCESS_KEY:?}"
    bucket="$(env_get BACKUP_S3_BUCKET)"
    fetch() {
        dc --profile ops run --rm --no-deps -v "$run_id:/drill" \
            -e RCLONE_CONFIG_OFFSITE_ACCESS_KEY_ID="$RESTORE_S3_ACCESS_KEY_ID" \
            -e RCLONE_CONFIG_OFFSITE_SECRET_ACCESS_KEY="$RESTORE_S3_SECRET_ACCESS_KEY" \
            offsite "$@"
    }
    [ -n "$archive" ] || archive="$(fetch lsf "offsite:$bucket/daily/" --files-only | sort | tail -n 1)"
    archive="${archive//$'\r'/}"
    log "downloading daily/$archive"
    fetch copyto "offsite:$bucket/daily/$archive" /drill/archive.zip
else
    [ -n "$archive" ] || archive="$(dc exec -T "$(app_service)" sh -c 'ls -1t storage/backups/mawred/*.zip | head -n 1')"
    archive="$(basename "${archive//$'\r'/}")"
    log "using local $archive"
    dc run --rm --no-deps --user root -v "$run_id:/drill" --entrypoint cp "$(app_service)" \
        "storage/backups/mawred/$archive" /drill/archive.zip
fi

# The app image has PHP's zip extension, which reads the AES-encrypted archive; the password comes
# from the container's own environment (.env.production), never the command line.
log "decrypting and unpacking"
# shellcheck disable=SC2016 # PHP code, expanded by PHP
dc run --rm --no-deps --user root -v "$run_id:/drill" --entrypoint php "$(app_service)" -r '
    $zip = new ZipArchive();
    if ($zip->open("/drill/archive.zip") !== true) { fwrite(STDERR, "cannot open the archive\n"); exit(1); }
    $zip->setPassword((string) getenv("BACKUP_ARCHIVE_PASSWORD"));
    if (! $zip->extractTo("/drill/x")) { fwrite(STDERR, "cannot extract: wrong BACKUP_ARCHIVE_PASSWORD or a damaged archive\n"); exit(1); }
    echo $zip->numFiles, " entries\n";'

user="$(env_get DB_USERNAME)"
log "loading the dump into a throwaway Postgres"
docker run -d --name "$run_id-pg" --network "$run_id" --memory 1536m \
    -e POSTGRES_USER="$user" -e POSTGRES_PASSWORD=drill -e POSTGRES_DB=drill \
    postgres:18-alpine >/dev/null
until docker exec "$run_id-pg" pg_isready -U "$user" -d drill >/dev/null 2>&1; do sleep 2; done
sleep 2

docker run --rm --network "$run_id" -v "$run_id:/drill:ro" -e PGPASSWORD=drill postgres:18-alpine \
    sh -c "set -e; dump=\$(ls /drill/x/db-dumps/*.sql | head -n 1); psql -q -v ON_ERROR_STOP=1 -h '$run_id-pg' -U '$user' -d drill -f \"\$dump\" >/dev/null"

query() { docker exec "$run_id-pg" psql -U "$user" -d drill -tAc "$1"; }
users="$(query 'SELECT count(*) FROM users')"
employees="$(query 'SELECT count(*) FROM employees')"
migrations="$(query 'SELECT count(*) FROM migrations')"
files="$(docker run --rm -v "$run_id:/drill:ro" alpine:3 sh -c 'find /drill/x -type f ! -path "/drill/x/db-dumps/*" | wc -l')"

# The deployed release must see every migration as run: the dump is complete and current.
log "checking migrations with $(current_tag)"
status="$(APP_KEY="$(env_get APP_KEY)" DB_PASSWORD=drill docker run --rm --network "$run_id" \
    -e APP_KEY -e DB_PASSWORD -e MAWRED_OPTIMIZE=false -e DB_CONNECTION=pgsql -e DB_HOST="$run_id-pg" \
    -e DB_PORT=5432 -e DB_DATABASE=drill -e DB_USERNAME="$user" -e CACHE_STORE=array \
    -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync -e LOG_CHANNEL=stderr \
    "$IMAGE:$(current_tag)" php artisan migrate:status --no-interaction)"
grep -q Ran <<<"$status" || die "migrate:status listed no migrations"
pending="$(grep -c Pending <<<"$status" || true)"
[ "$pending" = 0 ] || die "$pending migration(s) are pending against the restored database"

summary="$(printf 'Archive: %s (%s)\nUsers: %s, employees: %s, migrations: %s, files: %s\nTook %ss with release %s.' \
    "$archive" "$([ "$from_offsite" = yes ] && echo offsite || echo local)" \
    "$users" "$employees" "$migrations" "$files" "$((SECONDS - started))" "$(current_tag)")"
printf '%s %s\n' "$(date -u +%F)" "${summary//$'\n'/ | }" >>"$STATE_DIR/drills.log"
log "$summary"
alert "Restore drill passed" "$summary"
