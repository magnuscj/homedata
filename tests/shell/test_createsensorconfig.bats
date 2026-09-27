#!/usr/bin/env bats
# tests/shell/test_createsensorconfig.bats
#
# Exercises container/createSensorConfig.sh's reseed-when-no-named-rows fallback
# with a stubbed mysql and a temp STORAGE_DIR. No real database is touched.

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

@test "createSensorConfig: reseeds when no named rows exist" {
  export STUB_MYSQL_NAMED=0
  run bash "$REPO_ROOT/container/createSensorConfig.sh"
  [ "$status" -eq 0 ]
  [[ "$output" == *"applying hardcoded seed"* ]]
  # The SEED INSERT must have reached the mysql stub.
  grep -q "INSERT INTO sensorconfig" "$STUB_MYSQL_LOG"
}

@test "createSensorConfig: does NOT reseed when named rows are present" {
  export STUB_MYSQL_NAMED=25
  run bash "$REPO_ROOT/container/createSensorConfig.sh"
  [ "$status" -eq 0 ]
  [[ "$output" != *"applying hardcoded seed"* ]]
  # No seed INSERT should have been issued.
  ! grep -q "INSERT INTO sensorconfig" "$STUB_MYSQL_LOG"
}

@test "createSensorConfig: loads persisted dump when present" {
  export STUB_MYSQL_NAMED=25
  echo "-- persisted dump" > "$STORAGE_DIR/sensorconfig.sql"
  run bash "$REPO_ROOT/container/createSensorConfig.sh"
  [ "$status" -eq 0 ]
  [[ "$output" == *"Loading"*"sensorconfig.sql"* ]]
}
