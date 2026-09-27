#!/usr/bin/env bats
# tests/shell/test_backup.bats
#
# Exercises container/backup.sh's row-count gate and archive rotation with a
# stubbed mysql/mysqldump and a temp STORAGE_DIR. No real database is touched.

setup() {
  REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"
  STUBS="$BATS_TEST_DIRNAME/stubs"
  PATH="$STUBS:$PATH"
  export PATH

  STORAGE_DIR="$BATS_TEST_TMPDIR/storage"
  mkdir -p "$STORAGE_DIR"
  export STORAGE_DIR
  export STUB_MYSQL_LOG="$BATS_TEST_TMPDIR/mysql.log"
}

@test "backup: skips when row count below threshold" {
  export STUB_MYSQL_ROWS=500
  run bash "$REPO_ROOT/container/backup.sh"
  [ "$status" -eq 0 ]
  [[ "$output" == *"Skipping backup"* ]]
  # no archive should have been created
  run bash -c "ls '$STORAGE_DIR'/*.tar 2>/dev/null | wc -l"
  [ "$output" -eq 0 ]
}

@test "backup: creates archive when row count is sufficient" {
  export STUB_MYSQL_ROWS=5000
  run bash "$REPO_ROOT/container/backup.sh"
  [ "$status" -eq 0 ]
  [[ "$output" == *"Backup complete"* ]]
  [ -f "$STORAGE_DIR/test1.tar" ]
  [ -f "$STORAGE_DIR/sensorconfig.sql" ]
}

@test "backup: rotates and drops oldest when 10 archives already present" {
  export STUB_MYSQL_ROWS=5000
  # Pre-seed 10 archives test1..test10; test10 is the oldest by naming scheme.
  for n in $(seq 1 10); do echo "dump$n" > "$STORAGE_DIR/test$n.tar"; done
  run bash "$REPO_ROOT/container/backup.sh"
  [ "$status" -eq 0 ]
  [[ "$output" == *"Removing"* ]]
  # After rotation there should still be at most 10 archives (oldest dropped,
  # a fresh test1.tar created).
  count=$(ls "$STORAGE_DIR"/*.tar | wc -l)
  [ "$count" -le 10 ]
  [ -f "$STORAGE_DIR/test1.tar" ]
}
