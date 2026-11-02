#!/usr/bin/env bash
# Writes .env.production for a new server from deploy/env.production.example (D135): the host
# names and alert address filled in, and every secret Mawred makes for itself generated here with
# openssl, so none is typed, pasted or sent anywhere. Run as the deploy user:
#
#   /core/mawred/deploy/make-env.sh <app-host> <ops-email> [glitchtip-host]
#
# It never overwrites an existing .env.production and never prints a secret. Afterwards, fill in
# what only you know (it lists them): Postmark, the From address, the backup bucket. Then put a
# copy of the file in the password manager.

set -Eeuo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
example="$root_dir/deploy/env.production.example"
target="$root_dir/.env.production"
usage="usage: make-env.sh <app-host> <ops-email> [glitchtip-host]"

die() {
    printf 'error: %s\n' "$*" >&2
    exit 1
}

app_host="${1:?$usage}"
ops_email="${2:?$usage}"
glitchtip_host="${3:-}"

valid_host() { [[ "$1" =~ ^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?\.[a-z]{2,}$ ]] || die "not a host name: $1"; }
valid_host "$app_host"
[ -z "$glitchtip_host" ] || valid_host "$glitchtip_host"
[[ "$ops_email" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[a-z]{2,}$ ]] || die "not an email address: $ops_email"
[ ! -e "$target" ] || die "$target already exists; edit it instead (this script never overwrites it)"
command -v openssl >/dev/null || die "openssl is not installed"

umask 077
work="$(mktemp "$root_dir/.env.production.XXXXXX")"
trap 'rm -f "$work"' EXIT
cp "$example" "$work"

# Replaces KEY=… in the work file. Values here are host names, emails, hex or base64 only.
set_key() {
    grep -qE "^$1=" "$work" || die "$1 is missing from $example"
    awk -v key="$1" -v value="$2" 'index($0, key "=") == 1 { print key "=" value; next } { print }' "$work" >"$work.next"
    mv "$work.next" "$work"
}
hex() { openssl rand -hex "$1"; }

set_key APP_URL "https://$app_host"
set_key APP_HOST "$app_host"
set_key REVERB_BROWSER_HOST "$app_host"
set_key CAREERS_HOST ""
set_key GLITCHTIP_HOST "$glitchtip_host"
set_key ACME_EMAIL "$ops_email"
set_key OPS_ALERT_EMAILS "$ops_email"

set_key APP_KEY "base64:$(openssl rand -base64 32)"
set_key BLIND_INDEX_KEY "$(hex 32)"
set_key DB_PASSWORD "$(hex 24)"
set_key REVERB_APP_ID "$((100000 + $(od -An -N4 -tu4 /dev/urandom | tr -d ' ') % 900000))"
set_key REVERB_APP_KEY "$(hex 10)"
set_key REVERB_APP_SECRET "$(hex 32)"
set_key GLITCHTIP_SECRET_KEY "$(hex 32)"
set_key BACKUP_ARCHIVE_PASSWORD "$(hex 32)"

mv "$work" "$target"
trap - EXIT
chmod 600 "$target"

printf 'Wrote %s (chmod 600) for https://%s.\n' "$target" "$app_host"
printf 'Generated: APP_KEY, BLIND_INDEX_KEY, DB_PASSWORD, REVERB_APP_ID/KEY/SECRET, GLITCHTIP_SECRET_KEY, BACKUP_ARCHIVE_PASSWORD.\n\n'
printf 'Still yours to fill in (nano %s):\n' "$target"
grep -nE '^[A-Z0-9_]+=($|.*example\.com|.*<)' "$target" |
    grep -vE '^[0-9]+:(CAREERS_HOST|GLITCHTIP_HOST|SENTRY_LARAVEL_DSN|VAPID_PUBLIC_KEY|VAPID_PRIVATE_KEY|BACKUP_HEARTBEAT_URL)=' |
    sed 's/^/  line /' || true
printf '\nLater: VAPID keys (php artisan mawred:vapid-keys) and SENTRY_LARAVEL_DSN once GlitchTip runs.\n'
printf 'Then copy the whole file into the password manager.\n'
