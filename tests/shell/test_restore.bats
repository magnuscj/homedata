#!/usr/bin/env bats
# tests/shell/test_restore.bats
#
# Exercises container/restore.sh selection/fallback logic with a stubbed mysql
# and a temp STORAGE_DIR. No real database is touched.

setup() {
  REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"
  STUBS="$BATS_TEST_DIRNAME/stubs"
  PATH="$STUBS:$PATH"
  export PATH

  # Temp storage dir WITHOUT digits (restore.sh/backup.sh manipulate numeric
  # path components; production /usr/storage has none, so we mirror that).
  STORAGE_DIR="$BATS_TEST_TMPDIR/storage"
  mkdir -p "$STORAGE_DIR"
  export STORAGE_DIR

  # mysql stub logs here; DDL/import just succeed.
  export STUB_MYSQL_LOG="$BATS_TEST_TMPDIR/mysql.log"
}

# Build a valid backup tar. restore.sh extracts with `--strip-components=2`,
# expecting the archived member to be "<a>/<b>/test1.sql" (production stores
# "usr/storage/test1.sql"). We reproduce that 2-component layout via a staging
# dir so the strip leaves test1.sql at the workdir root regardless of the temp
# path depth. The content marker lets us assert which candidate was chosen.
make_valid_tar() {
  local index="$1" marker="$2"
  local stage="$BATS_TEST_TMPDIR/stage"
  mkdir -p "$stage/usr/storage"
  echo "$marker" > "$stage/usr/storage/test1.sql"
  ( cd "$stage" && tar -czf "$STORAGE_DIR/test$index.tar" usr/storage/test1.sql )
  rm -rf "$stage"
}

make_corrupt_tar() {
  local index="$1"
  echo "not a real tar" > "$STORAGE_DIR/test$index.tar"
}

@test "restore: no backups -> fresh DB, exit 0" {
  run bash "$REPO_ROOT/container/restore.sh"
  [ "$status" -eq 0 ]
  [[ "$output" == *"starting fresh"* ]]
}

@test "restore: picks the newest valid backup (test1 over test2)" {
  make_valid_tar 2 "OLD"
  make_valid_tar 1 "NEW"
  run bash "$REPO_ROOT/container/restore.sh"
  [ "$status" -eq 0 ]
  [[ "$output" == *"Restoring from:"*"test1.tar"* ]]
  [[ "$output" == *"Restore complete"* ]]
}

@test "restore: corrupt newest falls back to next-newest" {
  make_corrupt_tar 1        # newest is broken
  make_valid_tar 2 "FALLBACK"
  run bash "$REPO_ROOT/container/restore.sh"
  [ "$status" -eq 0 ]
  [[ "$output" == *"trying next"* ]]
  [[ "$output" == *"test2.tar"* ]]
  [[ "$output" == *"Restore complete"* ]]
}

@test "restore: all corrupt -> exit 1" {
  make_corrupt_tar 1
  make_corrupt_tar 2
  run bash "$REPO_ROOT/container/restore.sh"
  [ "$status" -eq 1 ]
  [[ "$output" == *"all backups"*"failed to restore"* ]]
}
