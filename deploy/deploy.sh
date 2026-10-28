#!/usr/bin/env bash
# Zero-downtime rollout with rollback (ADR-0019, D125). Run on the server as the deploy user,
# by hand or by .github/workflows/deploy.yml through deploy/ci-gate.sh.
#
#   deploy.sh <tag>        roll out ghcr.io/raghdkun/mawred-app:<tag> (the git SHA CI pushed)
#   deploy.sh rollback     go back to the previous tag (no migrations: they are expand-only)
#   deploy.sh init <tag>   first start on a new server: databases, migrations, every service
#   deploy.sh up           apply compose.prod.yaml changes to the other services (may restart them)
#   deploy.sh status       show the deployed tags and containers

# shellcheck source=deploy/lib.sh
source "$(dirname "$0")/lib.sh"

valid_tag() { [[ "$1" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$ ]] || die "not an image tag: $1"; }

# Waits until a container's Docker health check passes and /health/ready answers 200 inside it.
# A container that keeps crashing (restarted twice) or turns unhealthy fails at once.
wait_ready() {
    local id="$1" deadline=$((SECONDS + ${2:-240})) health
    while ((SECONDS < deadline)); do
        health="$(docker inspect -f '{{if ge .RestartCount 2}}crashing{{else if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$id" 2>/dev/null || echo gone)"
        case "$health" in
        gone | unhealthy | crashing) return 1 ;;
        healthy)
            docker exec "$id" curl -fsS -m 5 -o /dev/null http://127.0.0.1:8080/health/ready && return 0
            ;;
        esac
        sleep 3
    done
    return 1
}

# Through Caddy, as a visitor would arrive (TLS and all), but without leaving the machine.
smoke() {
    local host
    host="$(env_get APP_HOST)"
    curl -fsS -m 10 --retry 6 --retry-delay 5 --retry-all-errors -o /dev/null \
        --resolve "$host:443:127.0.0.1" "https://$host/health/ready"
}

# Starts <tag> beside the running app container, waits for it, then retires the old one; Caddy's
# upstream health checks move traffic across. Workers follow once the web tier is on <tag>.
# Callers test its status, which turns off `set -e` inside it, so every step checks itself.
rollout() {
    local tag="$1" migrate="$2" id new=""
    local -a old

    log "pulling $IMAGE:$tag" # commit tags never change, so a local copy is used as is
    MAWRED_TAG="$tag" dc pull --quiet --policy missing app || return 1

    if [ "$migrate" = yes ]; then
        log "running migrations (expand-only, the running release keeps working)"
        MAWRED_TAG="$tag" dc run --rm --no-deps -e MAWRED_OPTIMIZE=false app php artisan migrate --force --no-interaction || return 1
    fi

    mapfile -t old < <(dc ps -q app)
    log "starting $tag next to ${#old[@]} running app container(s)"
    MAWRED_TAG="$tag" dc up -d --no-deps --no-recreate --scale "app=$((${#old[@]} + 1))" app || return 1

    for id in $(dc ps -q app); do
        [[ " ${old[*]} " == *" $id "* ]] || new="$id"
    done
    if [ -z "$new" ]; then
        log "the new app container did not start"
        return 1
    fi

    if ! wait_ready "$new"; then
        log "the new container never became ready; the old one keeps serving"
        docker logs --tail 80 "$new" >&2 || true
        docker rm -f "$new" >/dev/null
        return 1
    fi

    sleep 3 # Caddy re-resolves the app upstreams every second
    for id in "${old[@]}"; do
        log "retiring $id"
        docker stop -t 30 "$id" >/dev/null && docker rm "$id" >/dev/null
    done

    # Horizon finishes its running jobs on SIGTERM (stop_grace_period in compose.prod.yaml).
    log "moving horizon, scheduler, reverb and ssr to $tag"
    MAWRED_TAG="$tag" dc up -d --no-deps horizon scheduler reverb ssr || return 1
    dc exec -T caddy caddy reload --config /etc/caddy/Caddyfile >/dev/null 2>&1 || log "caddy reload failed; it keeps its previous config"
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
        alert "Deploy of $tag failed" "The new release never became ready. $was is still serving. Logs: journalctl or docker compose logs app."
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
        alert "Deploy of $tag rolled back" "The release started but failed the smoke check through Caddy, so $was is serving again."
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
    local tag="$1" user database
    valid_tag "$tag"
    take_lock 0
    user="$(env_get DB_USERNAME)"
    database="$(env_get DB_DATABASE)"

    log "pulling $tag and the service images"
    MAWRED_TAG="$tag" dc pull --quiet --policy missing

    MAWRED_TAG="$tag" dc up -d --wait pgsql redis
    if ! dc exec -T pgsql psql -U "$user" -d "$database" -tAc "SELECT 1 FROM pg_database WHERE datname = 'glitchtip'" | grep -q 1; then
        log "creating the glitchtip database"
        dc exec -T pgsql createdb -U "$user" glitchtip
    fi

    MAWRED_TAG="$tag" dc run --rm --no-deps -e MAWRED_OPTIMIZE=false app php artisan migrate --force --no-interaction
    MAWRED_TAG="$tag" dc up -d
    wait_ready "$(dc ps -q app | head -n 1)" || die "the app never became ready; see docker compose logs app"
    record "$tag"
    smoke || log "warning: the smoke check through Caddy failed (DNS or the certificate may not be ready yet)"
    log "Mawred $tag is running. Next: php artisan mawred:install (deploy/runbooks/provision-server.md)"
}

case "${1:-}" in
rollback) rollback ;;
init) init "${2:?usage: deploy.sh init <tag>}" ;;
up)
    take_lock 0
    dc up -d --remove-orphans
    ;;
status)
    printf 'current:  %s\nprevious: %s\n\n' "$(current_tag)" "$(previous_tag)"
    dc ps
    ;;
"" | -h | --help) sed -n '2,10p' "$0" ;;
*) deploy "$1" ;;
esac
