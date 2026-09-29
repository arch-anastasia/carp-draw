#!/bin/sh
# Opt-in, one-shot verified dump. Run using the same PostgreSQL major as the server.
# Credentials come only from libpq's environment or .pgpass, never CLI arguments.
set -eu
umask 077
: "${PGDATABASE:?PGDATABASE is required}"
: "${BACKUP_DIR:?BACKUP_DIR is required}"
mkdir -p "$BACKUP_DIR"
partial=$(mktemp "$BACKUP_DIR/.dump-XXXXXX")
status=$(mktemp "$BACKUP_DIR/.dump-status-XXXXXX")
trap 'rm -f "$partial" "$status"' EXIT HUP INT TERM
if ! { pg_dump --no-owner --clean --if-exists; echo "$?" > "$status"; } | gzip > "$partial"; then
    echo 'Backup failed: gzip could not write the dump.' >&2
    exit 1
fi
if [ "$(cat "$status")" != '0' ]; then
    echo 'Backup failed: pg_dump did not complete.' >&2
    exit 1
fi
gzip -t "$partial"
if ! gzip -dc "$partial" | tail -n 8 | grep -q 'PostgreSQL database dump complete'; then
    echo 'Backup failed: dump completion marker is missing.' >&2
    exit 1
fi
name="scheduled-$(date -u +%Y%m%d-%H%M%S).sql.gz"
if [ -e "$BACKUP_DIR/$name" ]; then
    echo 'Backup failed: a dump with this timestamp already exists.' >&2
    exit 1
fi
mv "$partial" "$BACKUP_DIR/$name"
printf 'Verified dump: %s\n' "$name"
