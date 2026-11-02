#!/usr/bin/env bash
# Host checks every five minutes (ADR-0019), run by mawred-monitor.timer: disk, memory, containers,
# the host's nginx and pending reboots. Each problem emails OPS_ALERT_EMAILS once, then again every six hours while
# it lasts. The external uptime ping covers the whole host going down.

# shellcheck source=deploy/lib.sh
source "$(dirname "$0")/lib.sh"

alerts_dir="$STATE_DIR/alerts"
mkdir -p "$alerts_dir"

raise() {
    local marker="$alerts_dir/$1"
    if [ -z "$(find "$marker" -mmin -360 2>/dev/null)" ]; then
        alert "$2" "$3"
        touch "$marker"
    fi
}
settle() { rm -f "$alerts_dir/$1"; }

disk="$(df -P / | awk 'NR == 2 { sub("%", "", $5); print $5 }')"
if ((disk >= ${DISK_ALERT_PCT:-80})); then
    raise disk "Disk ${disk}% full" "$(df -h / | tail -n 1). Check docker system df, old images and the backups volume."
else
    settle disk
fi

available="$(awk '/MemTotal/ { t = $2 } /MemAvailable/ { a = $2 } END { printf "%d", a * 100 / t }' /proc/meminfo)"
if ((available <= 100 - ${MEMORY_ALERT_PCT:-90})); then
    raise memory "Memory ${available}% free" "$(free -h | sed -n 2p). See docker stats --no-stream and deploy/sizing.md."
else
    settle memory
fi

problems=""
for service in $(dc config --services) "$(app_service)"; do
    state="$(dc ps --all --format '{{.State}} {{.Health}}' "$service" 2>/dev/null | head -n 1)"
    case "$state" in
    "running unhealthy"* | "" | exited* | dead* | restarting*) problems+="$service: ${state:-no container}"$'\n' ;;
    esac
done
if ! systemctl is-active --quiet nginx; then
    problems+="nginx (host): $(systemctl is-active nginx 2>/dev/null || true)"$'\n'
fi
if [ -n "$problems" ]; then
    raise containers "A Mawred service is down" "$problems"$'\n'"See: deploy/deploy.sh status, docker compose logs <service>."
else
    settle containers
fi

if [ -f /var/run/reboot-required ]; then
    raise reboot "The server needs a reboot" "Security updates are waiting for a reboot: $(tr '\n' ' ' </var/run/reboot-required.pkgs 2>/dev/null). Plan one in a quiet hour."
else
    settle reboot
fi
