#!/usr/bin/env bash
# Zero-downtime blue/green rollout with rollback (ADR-0019, D125, D134). Run on the server as the deploy user,
# by hand or by .github/workflows/deploy.yml through deploy/ci-gate.sh.
#
#   deploy.sh <tag>        roll out ghcr.io/raghdkun/mawred-app:<tag> (the git SHA CI pushed)
#   deploy.sh rollback     go back to the previous tag (no migrations: they are expand-only)
#   deploy.sh init <tag>   first start on a new server: databases, migrations, every service
#   deploy.sh up           apply compose.prod.yaml changes to the other services (may restart
#                          them); the app slots pick up changes on the next deploy
#   deploy.sh status       show the deployed tags, the live slot and the containers

# shellcheck source=deploy/lib.sh
source "$(dirname "$0")/lib.sh"

valid_tag() { [[ "$1" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$ ]] || die "not an image tag: $1"; }

# Waits until a slot's container passes its Docker health check and /health/ready answers 200 on
# the loopback port nginx will use. A container that keeps crashing (restarted twice) or turns
# unhealthy fails at once.
wait_ready() {
    local id="$1" port="$2" deadline=$((SECONDS + ${3:-240})) health
    while ((SECONDS < deadline)); do
        health="$(docker inspect -f '{{if ge .RestartCount 2}}crashing{{else if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$id" 2>/dev/null || echo gone)"
        case "$health" in
        gone | unhealthy | crashing) return 1 ;;
        healthy)
            curl -fsS -m 5 -o /dev/null "http://127.0.0.1:$port/health/ready" && return 0
            ;;
        esac
        sleep 3
    done
    return 1
}

# Through nginx, as a visitor would arrive (TLS and all), but without leaving the machine.
smoke() {
    local host
    host="$(env_get APP_HOST)"
    curl -fsS -m 10 --retry 6 --retry-delay 5 --retry-all-errors -o /dev/null \
        --resolve "$host:443:127.0.0.1" "https://$host/health/ready"
}

# Starts <tag> in the idle slot, waits for it, points nginx at it, then retires the live slot
# (D134). Workers follow once the web tier is on <tag>. Callers test its status, which turns off
# `set -e` inside it, so every step checks itself.
rollout() {
    local tag="$1" migrate="$2" from to id
    from="$(live_slot)"
    to="$(idle_slot)"

    log "pulling $IMAGE:$tag" # commit tags never change, so a local copy is used as is
    MAWRED_TAG="$tag" dc pull --quiet --policy missing "app-$to" || return 1

    if [ "$migrate" = yes ]; then
        log "running migrations (expand-only, the running release keeps working)"
        MAWRED_TAG="$tag" dc run --rm --no-deps -e MAWRED_OPTIMIZE=false "app-$to" php artisan migrate --force --no-interaction || return 1
    fi

    dc rm -sf "app-$to" >/dev/null 2>&1 || true # left over from an interrupted deploy
    log "starting $tag in the $to slot (127.0.0.1:$(slot_port "$to")); $from keeps serving"
    MAWRED_TAG="$tag" dc up -d --no-deps "app-$to" || return 1
    id="$(dc ps -q "app-$to")"

    if [ -z "$id" ] || ! wait_ready "$id" "$(slot_port "$to")"; then
        log "the $to slot never became ready; $from keeps serving"
        [ -z "$id" ] || docker logs --tail 80 "$id" >&2 || true
        dc rm -sf "app-$to" >/dev/null 2>&1 || true
        return 1
    fi

    if ! point_nginx "$to"; then
        log "nginx refused the switch (see: sudo nginx -t); $from keeps serving"
        dc rm -sf "app-$to" >/dev/null 2>&1 || true
        return 1
    fi

    sleep 5 # nginx's old workers finish the requests they already sent to the old slot
    if [ -n "$(dc ps -q "app-$from")" ]; then
        log "retiring the $from slot"
        dc stop -t 30 "app-$from" >/dev/null && dc rm -f "app-$from" >/dev/null
    fi

    # Horizon finishes its running jobs on SIGTERM (stop_grace_period in compose.prod.yaml).
    log "moving horizon, scheduler, reverb and ssr to $tag"
    MAWRED_TAG="$tag" dc up -d --no-deps horizon scheduler reverb ssr || return 1
}

record() {
    local tag="$1" was
    was="$(current_tag)"
    [ "$was" = "$tag" ] || printf '%s\n' "$was" >"$STATE_DIR/previous-tag"
    printf '%s\n' "$tag" >"$STATE_DIR/current-tag"
}

# Keeps the deployed and previous images (rollback) and drops older ones.
prune_images() {
    local keep_current keep_previous
    keep_current="$(current_tag)"
    keep_previous="$(previous_tag)"
    docker images "$IMAGE" --format '{{.Tag}}' |
        grep -vxF -e "$keep_current" -e "${keep_previous:-$keep_current}" -e '<none>' |
        while read -r tag; do docker rmi "$IMAGE:$tag" >/dev/null 2>&1 || true; done
    docker image prune -f >/dev/null
}

deploy() {
    local tag="$1" was
    valid_tag "$tag"
    take_lock 0
    was="$(current_tag)"

    if ! rollout "$tag" yes; then
        alert "Deploy of $tag failed" "The new release never became ready, or nginx refused the switch. $was is still serving. See deploy/runbooks/deploy-and-rollback.md."
        exit 1
    fi

    if smoke; then
        record "$tag"
        prune_images
        log "deployed $tag"
        return 0
    fi

    log "smoke check failed; rolling back to $was"
    if rollout "$was" no && smoke; then
        alert "Deploy of $tag rolled back" "The release started but failed the smoke check through nginx, so $was is serving again."
    else
        alert "Deploy of $tag failed and rollback to $was failed" "Mawred may be down. Follow deploy/runbooks/deploy-and-rollback.md now."
    fi
    exit 1
}

rollback() {
    local to
    to="$(previous_tag)"
    [ -n "$to" ] || die "there is no previous tag to roll back to"
    take_lock 0
    rollout "$to" no || die "rollback to $to failed; the current release keeps serving"
    smoke || die "rollback to $to is running but fails the smoke check"
    record "$to"
    log "rolled back to $to"
}

init() {
    local tag="$1" user database slot
    valid_tag "$tag"
    take_lock 0
    user="$(env_get DB_USERNAME)"
    database="$(env_get DB_DATABASE)"
    slot="$(live_slot)"

    log "pulling $tag and the service images"
    MAWRED_TAG="$tag" dc pull --quiet --policy missing
    MAWRED_TAG="$tag" dc pull --quiet --policy missing "app-$slot"

    MAWRED_TAG="$tag" dc up -d --wait pgsql redis
    if ! dc exec -T pgsql psql -U "$user" -d "$database" -tAc "SELECT 1 FROM pg_database WHERE datname = 'glitchtip'" | grep -q 1; then
        log "creating the glitchtip database"
        dc exec -T pgsql createdb -U "$user" glitchtip
    fi

    MAWRED_TAG="$tag" dc run --rm --no-deps -e MAWRED_OPTIMIZE=false "app-$slot" php artisan migrate --force --no-interaction
    MAWRED_TAG="$tag" dc up -d
    MAWRED_TAG="$tag" dc up -d --no-deps "app-$slot"
    wait_ready "$(dc ps -q "app-$slot")" "$(slot_port "$slot")" || die "the app never became ready; see deploy/dc.sh logs app-$slot"
    record "$tag"
    printf '%s\n' "$slot" >"$STATE_DIR/live-slot"
    if point_nginx "$slot" && smoke; then
        log "Mawred $tag is running behind nginx. Next: php artisan mawred:install (deploy/runbooks/provision-server.md)"
    else
        log "Mawred $tag is running on 127.0.0.1:$(slot_port "$slot"). Next: sudo $MAWRED_ROOT/deploy/nginx-site.sh, then php artisan mawred:install (deploy/runbooks/provision-server.md)"
    fi
}

case "${1:-}" in
rollback) rollback ;;
init) init "${2:?usage: deploy.sh init <tag>}" ;;
up)
    take_lock 0
    dc up -d --remove-orphans
    ;;
status)
    printf 'current:  %s\nprevious: %s\nlive:     %s (127.0.0.1:%s)\n\n' "$(current_tag)" "$(previous_tag)" "$(live_slot)" "$(slot_port "$(live_slot)")"
    dc ps
    ;;
"" | -h | --help) sed -n '2,11p' "$0" ;;
*) deploy "$1" ;;
esac
