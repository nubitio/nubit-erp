#!/bin/sh

# A worker must not reuse the web health endpoint. The worker has no HTTP
# server, and checking only that PHP is alive can leave a wedged consumer
# marked healthy. This probe deliberately stays dependency-free: Docker can
# use it before Redis/DB are available, while the worker process itself is
# still being supervised by restart: unless-stopped.

set -eu

expected_command="${1:-messenger:consume}"
heartbeat_file="${2:-}"
cmdline=$(tr '\000' ' ' < /proc/1/cmdline 2>/dev/null || true)

case "${cmdline}" in
    *"${expected_command}"*)
        ;;
    *)
        echo "worker health check failed: process command does not contain '${expected_command}': ${cmdline}" >&2
        exit 1
        ;;
esac

if [ -n "${heartbeat_file}" ]; then
    if [ ! -f "${heartbeat_file}" ]; then
        echo "worker health check failed: heartbeat file does not exist: ${heartbeat_file}" >&2
        exit 1
    fi

    now=$(date +%s)
    heartbeat_mtime=$(stat -c %Y "${heartbeat_file}" 2>/dev/null || stat -f %m "${heartbeat_file}")
    heartbeat_age=$((now - heartbeat_mtime))
    if [ "${heartbeat_age}" -gt 90 ]; then
        echo "worker health check failed: heartbeat is ${heartbeat_age}s old: ${heartbeat_file}" >&2
        exit 1
    fi
fi
