#!/usr/bin/env bats
# tests/shell/test_supervise_eds.bats
#
# Unit tests for the eds collector supervision (edssensors/supervise_eds.sh and
# edssensors/start_eds.sh) using a fake `eds` binary and stubbed mysql/pgrep.
# No real collector or database is involved. Exercises the behaviour that
# matters: the supervisor waits for mysqld then respawns ./eds when it exits,
# and start_eds.sh restarts (kill + let supervisor respawn) vs one-shot
# fallback. Overridable knobs (EDS_DIR/IPS_FILE/EDS_LOG/SUPERVISE_*) keep the
# test off the production paths.

setup() {
  REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"

  EDS_DIR="$BATS_TEST_TMPDIR/eds"
  mkdir -p "$EDS_DIR"
  IPS_FILE="$BATS_TEST_TMPDIR/ips.txt"
  echo "127.0.0.1" > "$IPS_FILE"
  EDS_LOG="$BATS_TEST_TMPDIR/eds_output.txt"
  : > "$EDS_LOG"
  export EDS_DIR IPS_FILE EDS_LOG

  # Fake `eds` binary: records one launch then exits immediately so the
  # supervisor loop iterates quickly.
  cat > "$EDS_DIR/eds" <<'FAKE'
#!/bin/bash
echo "fake-eds-run" >> "$EDS_RUN_LOG"
exit 0
FAKE
  chmod +x "$EDS_DIR/eds"
  export EDS_RUN_LOG="$BATS_TEST_TMPDIR/eds_runs.txt"
  : > "$EDS_RUN_LOG"

  # Stubs on PATH.
  STUBDIR="$BATS_TEST_TMPDIR/bin"
  mkdir -p "$STUBDIR"
  export PATH="$STUBDIR:$PATH"
}

make_mysql_stub() {
  # $1 = number of initial failures before SELECT 1 succeeds (default 0)
  local fails="${1:-0}"
  cat > "$BATS_TEST_TMPDIR/bin/mysql" <<STUB
#!/bin/bash
F="$BATS_TEST_TMPDIR/mysql_calls"
n=\$(cat "\$F" 2>/dev/null || echo 0); n=\$((n+1)); echo "\$n" > "\$F"
if [[ "\$n" -le $fails ]]; then exit 1; fi
exit 0
STUB
  chmod +x "$BATS_TEST_TMPDIR/bin/mysql"
}

@test "supervise_eds: respawns the collector each time it exits" {
  make_mysql_stub 0
  export SUPERVISE_MAX_ITERS=3 SUPERVISE_BACKOFF=0
  run timeout 20 bash "$REPO_ROOT/edssensors/supervise_eds.sh"
  [ "$status" -eq 0 ]
  # ./eds was (re)launched exactly 3 times.
  run grep -c "fake-eds-run" "$EDS_RUN_LOG"
  [ "$output" -eq 3 ]
  # The log reflects launches + the max-iters stop.
  grep -q "launching eds" "$EDS_LOG"
  grep -q "respawning in 0s" "$EDS_LOG"
  grep -q "reached SUPERVISE_MAX_ITERS=3" "$EDS_LOG"
}

@test "supervise_eds: waits for mysqld before the first launch" {
  make_mysql_stub 2            # fail SELECT 1 twice, then succeed
  export SUPERVISE_MAX_ITERS=1 SUPERVISE_BACKOFF=0
  run timeout 20 bash "$REPO_ROOT/edssensors/supervise_eds.sh"
  [ "$status" -eq 0 ]
  grep -q "waiting for mysqld" "$EDS_LOG"
  run grep -c "fake-eds-run" "$EDS_RUN_LOG"
  [ "$output" -eq 1 ]          # still launched once mysqld came up
}

@test "start_eds: with a supervisor running, it does NOT one-shot launch" {
  # pgrep stub: -x eds -> a pid (collector running); -f supervise_eds.sh -> found.
  # (kill is a bash builtin and cannot be PATH-stubbed; killing the fake pid is a
  # harmless no-op here. The meaningful assertion is that the fallback launch is
  # skipped when a supervisor is present.)
  cat > "$BATS_TEST_TMPDIR/bin/pgrep" <<'STUB'
#!/bin/bash
if [[ "$1" == "-x" && "$2" == "eds" ]]; then echo 4242; exit 0; fi
if [[ "$1" == "-f" && "$2" == *supervise_eds.sh* ]]; then exit 0; fi
exit 1
STUB
  chmod +x "$BATS_TEST_TMPDIR/bin/pgrep"

  run bash "$REPO_ROOT/edssensors/start_eds.sh"
  [ "$status" -eq 0 ]
  # supervisor present -> fallback one-shot launch must NOT have run.
  [ ! -s "$EDS_RUN_LOG" ]
}

@test "start_eds: with NO supervisor, it falls back to a one-shot launch" {
  cat > "$BATS_TEST_TMPDIR/bin/pgrep" <<'STUB'
#!/bin/bash
if [[ "$1" == "-x" && "$2" == "eds" ]]; then exit 1; fi            # no collector
if [[ "$1" == "-f" && "$2" == *supervise_eds.sh* ]]; then exit 1; fi # no supervisor
exit 1
STUB
  chmod +x "$BATS_TEST_TMPDIR/bin/pgrep"

  run bash "$REPO_ROOT/edssensors/start_eds.sh"
  [ "$status" -eq 0 ]
  sleep 1
  grep -q "fake-eds-run" "$EDS_RUN_LOG"              # one-shot launch happened
}
