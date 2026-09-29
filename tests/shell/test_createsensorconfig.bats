#!/usr/bin/env bats
# tests/shell/test_createsensorconfig.bats
#
# Unit-level checks for container/createSensorConfig.sh with a stubbed mysql and
# temp STORAGE_DIR/SCRIPT_DIR. No real database is touched. These assert the
# STRUCTURE of what the script does (never DROP the live table, always apply the
# migration, always fill-only merge the SEED). The end-to-end invariant "a named
# row is never demoted" is proven against a real MySQL in the integration tier
# (tests/integration/test_sensorconfig_invariant.bats).

setup() {
  REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"
  STUBS="$BATS_TEST_DIRNAME/stubs"
  PATH="$STUBS:$PATH"
  export PATH

  STORAGE_DIR="$BATS_TEST_TMPDIR/storage"
  mkdir -p "$STORAGE_DIR"
  export STORAGE_DIR

  # Point the script at the real migration file in the repo.
  SCRIPT_DIR="$REPO_ROOT/container"
  export SCRIPT_DIR

  export STUB_MYSQL_LOG="$BATS_TEST_TMPDIR/mysql.log"
}

@test "createSensorConfig: never DROPs the live sensorconfig table" {
  run bash "$REPO_ROOT/container/createSensorConfig.sh"
  [ "$status" -eq 0 ]
  # The live table must only ever be CREATE TABLE IF NOT EXISTS, never dropped.
  ! grep -qiE "drop table (if exists )?\`?sensorconfig\`?( |;)" "$STUB_MYSQL_LOG"
  grep -qi "create table if not exists" "$STUB_MYSQL_LOG"
}

@test "createSensorConfig: applies the sensorid uniqueness migration" {
  run bash "$REPO_ROOT/container/createSensorConfig.sh"
  [ "$status" -eq 0 ]
  [[ "$output" == *"uniqueness migration"* ]]
}

@test "createSensorConfig: always fill-only merges the hardcoded SEED" {
  run bash "$REPO_ROOT/container/createSensorConfig.sh"
  [ "$status" -eq 0 ]
  [[ "$output" == *"Merging hardcoded SEED"* ]]
  # The SEED merge must use ON DUPLICATE KEY UPDATE guarded by sensorname='name'
  # (fill-only), never a blind overwrite.
  grep -qi "on duplicate key update" "$STUB_MYSQL_LOG"
  grep -q "sensorconfig.sensorname = 'name'" "$STUB_MYSQL_LOG"
}

@test "createSensorConfig: merges a persisted PVC dump via a staging table (never piped raw into mydb)" {
  cat > "$STORAGE_DIR/sensorconfig.sql" <<'DUMP'
DROP TABLE IF EXISTS `sensorconfig`;
INSERT INTO `sensorconfig` VALUES (1,'16261054761367928446','name','black','false','default');
DUMP
  run bash "$REPO_ROOT/container/createSensorConfig.sh"
  [ "$status" -eq 0 ]
  [[ "$output" == *"Merging"*"sensorconfig.sql (fill-only)"* ]]
  # The dump's DROP TABLE must have been neutralised (redirected to a throwaway
  # name / staging), so it can never drop the live table.
  ! grep -qiE "drop table if exists \`sensorconfig\`" "$STUB_MYSQL_LOG"
  grep -qi "sensorconfig_stage" "$STUB_MYSQL_LOG"
}
