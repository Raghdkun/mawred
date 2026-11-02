#!/usr/bin/env bash
# docker compose for production, pinned to the deployed image tag. For people at the shell:
#   deploy/dc.sh ps          deploy/dc.sh logs --tail 200 app
#   deploy/dc.sh exec app php artisan about
# `app` means the live blue/green slot (app-blue or app-green, D134); name a slot to reach the
# other one. (MAWRED_TAG=<tag> deploy/dc.sh … targets another tag.)

# shellcheck source=deploy/lib.sh
source "$(dirname "$0")/lib.sh"

args=()
for arg in "$@"; do
    if [ "$arg" = app ]; then
        args+=("$(app_service)")
    else
        args+=("$arg")
    fi
done
dc "${args[@]}"
