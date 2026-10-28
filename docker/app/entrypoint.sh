#!/bin/sh
# Warms Laravel's caches inside each container before it starts (ADR-0019 step 3): config, events,
# routes and views are built from this container's own environment. MAWRED_OPTIMIZE=false skips it.
set -e

if [ "${MAWRED_OPTIMIZE:-true}" = "true" ] && [ "$1" != "sh" ] && [ "$1" != "bash" ]; then
    php artisan optimize --no-interaction >/dev/null
fi

exec docker-php-entrypoint "$@"
