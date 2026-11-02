#!/usr/bin/env bash
# Shared helpers for the server scripts (ADR-0019). Sourced by deploy.sh, backup.sh,
# restore-drill.sh and monitor.sh; never run on its own.
#
# Layout on the server (deploy/README.md):
#   /core/mawred/compose.prod.yaml, deploy/   the deploy bundle (sent by CI)
#   /core/mawred/.env.production              secrets, chmod 600, never in git
#   /core/mawred/.deploy/                     current-tag, previous-tag, live-slot,
#                                             nginx-upstream.conf, lock, alerts

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

# The app runs in one of two slots (D134): the live one serves through nginx, the idle one takes
# the next release. compose.prod.yaml publishes them on 127.0.0.1 (MAWRED_BLUE_PORT, default 8081;
# MAWRED_GREEN_PORT, default 8082).
live_slot() { cat "$STATE_DIR/live-slot" 2>/dev/null || echo blue; }
idle_slot() { if [ "$(live_slot)" = blue ]; then echo green; else echo blue; fi; }
slot_port() {
    local port
    if [ "$1" = blue ]; then
        port="$(env_get MAWRED_BLUE_PORT)"
        echo "${port:-8081}"
    else
        port="$(env_get MAWRED_GREEN_PORT)"
        echo "${port:-8082}"
    fi
}
app_service() { echo "app-$(live_slot)"; }

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
    dc exec -T "$(app_service)" php artisan ops:alert "$1" "$2" >/dev/null 2>&1 || log "could not send the alert through the app"
}

# Points nginx's app upstream at a slot and reloads it (graceful: requests already sent finish on
# the old slot). The deploy user may run exactly these two commands as root (harden.sh, sudoers).
# If nginx rejects the change, the previous upstream stays.
point_nginx() {
    local file="$STATE_DIR/nginx-upstream.conf" before=""
    [ -f "$file" ] && before="$(cat "$file")"
    printf '# Written by deploy/deploy.sh: the live app slot (%s).\nserver 127.0.0.1:%s;\n' "$1" "$(slot_port "$1")" >"$file.tmp"
    mv "$file.tmp" "$file"
    if sudo -n /usr/sbin/nginx -t -q && sudo -n /usr/bin/systemctl reload nginx; then
        printf '%s\n' "$1" >"$STATE_DIR/live-slot"
        return 0
    fi
    if [ -n "$before" ]; then
        printf '%s\n' "$before" >"$file"
    fi
    return 1
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
