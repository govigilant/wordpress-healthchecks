#!/usr/bin/env bash
set -euo pipefail

if [ -x /usr/local/bin/healthchecks-setup.sh ]; then
    /usr/local/bin/healthchecks-setup.sh
fi

exec /usr/local/bin/docker-entrypoint.sh "$@"
