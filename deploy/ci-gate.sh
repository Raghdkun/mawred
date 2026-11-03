#!/usr/bin/env bash
# Forced command for the CI deploy key (authorized_keys: restrict,command="…/deploy/ci-gate.sh").
# The key can only receive the deploy bundle and start a deploy of a commit SHA: no shell, no
# forwarding. harden.sh installs it.

set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"

case "${SSH_ORIGINAL_COMMAND:-}" in
bundle)
    exec tar -xzf - -C "$root" --no-same-owner compose.prod.yaml deploy
    ;;
"deploy "*)
    tag="${SSH_ORIGINAL_COMMAND#deploy }"
    [[ "$tag" =~ ^[0-9a-f]{40}$ ]] || {
        echo "deploy takes a full commit SHA" >&2
        exit 64
    }
    exec "$root/deploy/deploy.sh" "$tag"
    ;;
*)
    echo "this key may only send the bundle or deploy a commit" >&2
    exit 64
    ;;
esac
