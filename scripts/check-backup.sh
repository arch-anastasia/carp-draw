#!/bin/sh
# Restore rehearsal against the disposable CI PostgreSQL service, never production.
set -eu
: "${TEST_POSTGRES_CONTAINER:?Disposable PostgreSQL container required}"
: "${TEST_PG_USER:?Test database user required}"
: "${TEST_PG_PASSWORD:?Test database password required}"
: "${TEST_DATABASE:=carpdraw_ci}"
case "$TEST_DATABASE" in carpdraw_ci|carpdraw_foundation_test) ;; *) exit 2 ;; esac
exec_pg() {
    docker exec -e PGHOST=127.0.0.1 -e "PGUSER=$TEST_PG_USER" -e "PGPASSWORD=$TEST_PG_PASSWORD" "$TEST_POSTGRES_CONTAINER" "$@"
}
exec_pg psql -d "$TEST_DATABASE" -v ON_ERROR_STOP=1 -c "CREATE TABLE IF NOT EXISTS backup_probe (value text); INSERT INTO backup_probe VALUES ('carpdraw-restore-proof');" >/dev/null
docker cp scripts/backup-postgres.sh "$TEST_POSTGRES_CONTAINER:/tmp/backup-postgres.sh"
dump_dir="/tmp/carpdraw-backup-check-$$"
docker exec -e PGHOST=127.0.0.1 -e "PGUSER=$TEST_PG_USER" -e "PGPASSWORD=$TEST_PG_PASSWORD" -e "PGDATABASE=$TEST_DATABASE" -e "BACKUP_DIR=$dump_dir" "$TEST_POSTGRES_CONTAINER" sh /tmp/backup-postgres.sh
restore_db="carpdraw_restore_$$"
exec_pg createdb "$restore_db"
exec_pg sh -c 'set -eu; gzip -dc "$1"/scheduled-*.sql.gz > /tmp/carpdraw-restore.sql; psql -d "$2" -v ON_ERROR_STOP=1 -f /tmp/carpdraw-restore.sql >/dev/null' sh "$dump_dir" "$restore_db"
value=$(exec_pg psql -d "$restore_db" -Atc 'SELECT value FROM backup_probe LIMIT 1')
[ "$value" = 'carpdraw-restore-proof' ]
printf 'Verified backup restored successfully into an isolated database.\n'
# A failed pg_dump must leave no file eligible for backup:sync-r2.
if docker exec -e PGHOST=127.0.0.1 -e "PGUSER=$TEST_PG_USER" -e "PGPASSWORD=$TEST_PG_PASSWORD" -e PGDATABASE=missing_backup_test_db -e "BACKUP_DIR=$dump_dir/failed" "$TEST_POSTGRES_CONTAINER" sh /tmp/backup-postgres.sh; then
    echo 'Expected a nonexistent database dump to fail.' >&2
    exit 1
fi
exec_pg sh -c 'test -z "$(find "$1" -name "scheduled-*.sql.gz")"' sh "$dump_dir/failed"
printf 'Failed dumps are rejected, never published as restorable backups.\n'
