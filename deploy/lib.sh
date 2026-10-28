#!/usr/bin/env bash
# Shared helpers for the server scripts (ADR-0019). Sourced by deploy.sh, backup.sh,
# restore-drill.sh and monitor.sh; never run on its own.
#
# Layout on the server (deploy/README.md):
#   /srv/mawred/compose.prod.yaml, docker/caddy/, deploy/   the deploy bundle (sent by CI)
#   /srv/mawred/.env.production                            secrets, chmod 600, never in git
#   /srv/mawred/.deploy/                                    current-tag, previous-tag, lock, alerts

set -Eeuo pipefail

MAWRED_ROOT="${MAWRED_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
STATE_DIR="$MAWRED_ROOT/.deploy"
ENV_FILE="$MAWRED_ROOT/.env.production"
# shellcheck disable=SC2034 # read by the scripts that source this file
IMAGE="ghcr.io/raghdkun/mawred-app"

mkdir -p "$STATE_DIR"

log() { printf '%s %s\n' "$(date -u +%FT%TZ)" "$*" >&2; }
die() {
    log "error: $*"
    exit 1
}

[ -f "$ENV_FILE" ] || die "$ENV_FILE is missing (see deploy/env.production.example)"

# Reads one KEY from .env.production without sourcing it (values may hold shell characters).
env_get() {
    local line
    line="$(grep -E "^$1=" "$ENV_FILE" | tail -n 1 || true)"
    line="${line#*=}"
    line="${line%\"}"
    line="${line#\"}"
    printf '%s' "$line"
}

current_tag() { cat "$STATE_DIR/current-tag" 2>/dev/null || echo latest; }
previous_tag() { cat "$STATE_DIR/previous-tag" 2>/dev/null || true; }

# docker compose for the production project, pinned to the deployed image unless MAWRED_TAG is set.
dc() {
    MAWRED_TAG="${MAWRED_TAG:-$(current_tag)}" docker compose \
        --project-directory "$MAWRED_ROOT" \
        -f "$MAWRED_ROOT/compose.prod.yaml" \
        --env-file "$ENV_FILE" \
        "$@"
}

# Emails OPS_ALERT_EMAILS through the app. Never fails the caller: if the app is down, the
# external uptime ping is what notices.
alert() {
    log "alert: $1"
    dc exec -T app php artisan ops:alert "$1" "$2" >/dev/null 2>&1 || log "could not send the alert through the app"
}

# Pings a heartbeat URL (healthchecks.io style: <url> on success, <url>/fail on failure).
heartbeat() {
    local url
    url="$(env_get BACKUP_HEARTBEAT_URL)"
    [ -n "$url" ] || return 0
    curl -fsS -m 10 --retry 3 -o /dev/null "${url%/}${1:+/$1}" || log "heartbeat ping failed"
}

# One deploy, backup or drill at a time. $1 = seconds to wait for the others (0 = fail at once).
take_lock() {
    exec 9>"$STATE_DIR/lock"
    flock -w "${1:-0}" 9 || die "another deploy, backup or restore drill is running"
}
