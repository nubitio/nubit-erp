#!/bin/sh

# A worker must not reuse the web health endpoint. The worker has no HTTP
# server, and checking only that PHP is alive can leave a wedged consumer
# marked healthy. This probe deliberately stays dependency-free: Docker can
# use it before Redis/DB are available, while the worker process itself is
# still being supervised by restart: unless-stopped.

set -eu

expected_command="${1:-messenger:consume}"
cmdline=$(tr '\000' ' ' < /proc/1/cmdline 2>/dev/null || true)

case "${cmdline}" in
    *"${expected_command}"*)
        exit 0
        ;;
    *)
        echo "worker health check failed: process command does not contain '${expected_command}': ${cmdline}" >&2
        exit 1
        ;;
esac
