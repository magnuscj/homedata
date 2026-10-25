#!/usr/bin/env bats
# tests/shell/test_generate_ips.bats
#
# Unit tests for edssensors/generate_ips.sh — the boot-time generator of the
# eds collector poll list. Uses a stubbed discover_services.sh (no cluster/API)
# and overridable DISCOVER/STATIC_FILE/IPS_FILE so nothing touches the real
# /usr/storage or Kubernetes. Covers the behaviour that matters:
#   * output = discovered IPs THEN static IPs, de-duplicated, order preserved;
#   * discovery failure keeps an existing non-empty ips.txt (never clobbers);
#   * an empty result is refused;
#   * the SET of IPs equals the known sandbox poll list (regression guard).

setup() {
  REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"
  GEN="$REPO_ROOT/edssensors/generate_ips.sh"

  DISCOVER="$BATS_TEST_TMPDIR/discover_services.sh"
  STATIC_FILE="$BATS_TEST_TMPDIR/static-services.txt"
  IPS_FILE="$BATS_TEST_TMPDIR/ips.txt"
  export DISCOVER STATIC_FILE IPS_FILE
}

# Build a fake discover_services.sh that prints the given IPs for --ips-only.
# Call: make_discover <rc> <ip...>   (rc=exit code; if non-zero, prints nothing)
make_discover() {
  local rc="$1"; shift
  {
    echo '#!/bin/bash'
    echo "if [[ \"\$1\" == \"--ips-only\" ]]; then"
    for ip in "$@"; do echo "  echo $ip"; done
    echo "fi"
    echo "exit $rc"
  } > "$DISCOVER"
  chmod +x "$DISCOVER"
}

@test "generate_ips: writes discovered IPs then static IPs, order preserved" {
  make_discover 0 10.96.79.110 10.96.7.106 10.96.45.60
  printf '# comment\n\n192.168.50.230\n192.168.50.79\n' > "$STATIC_FILE"

  run bash "$GEN"
  [ "$status" -eq 0 ]

  # Exact ordered content: discovered (as emitted) then static (comments gone).
  run cat "$IPS_FILE"
  [ "${lines[0]}" = "10.96.79.110" ]
  [ "${lines[1]}" = "10.96.7.106" ]
  [ "${lines[2]}" = "10.96.45.60" ]
  [ "${lines[3]}" = "192.168.50.230" ]
  [ "${lines[4]}" = "192.168.50.79" ]
  [ "${#lines[@]}" -eq 5 ]
}

@test "generate_ips: de-duplicates (e.g. a static IP also discovered)" {
  make_discover 0 10.96.79.110 192.168.50.230
  printf '192.168.50.230\n192.168.50.79\n' > "$STATIC_FILE"

  run bash "$GEN"
  [ "$status" -eq 0 ]
  run grep -c '192.168.50.230' "$IPS_FILE"
  [ "$output" -eq 1 ]
}

@test "generate_ips: discovery failure keeps an existing non-empty ips.txt" {
  echo "1.2.3.4" > "$IPS_FILE"           # pre-existing good list
  make_discover 1                         # discovery fails
  printf '192.168.50.230\n' > "$STATIC_FILE"

  run bash "$GEN"
  [ "$status" -eq 0 ]
  run cat "$IPS_FILE"
  [ "$output" = "1.2.3.4" ]               # unchanged — not clobbered
}

@test "generate_ips: empty result with no existing file is refused" {
  make_discover 1                         # discovery fails
  : > "$STATIC_FILE"                      # no static entries
  rm -f "$IPS_FILE"                       # no existing file

  run bash "$GEN"
  [ "$status" -ne 0 ]
  [ ! -f "$IPS_FILE" ]                    # nothing written
}

@test "generate_ips: no static file -> just the discovered IPs" {
  make_discover 0 10.96.79.110 10.96.7.106
  rm -f "$STATIC_FILE"

  run bash "$GEN"
  [ "$status" -eq 0 ]
  run cat "$IPS_FILE"
  [ "${lines[0]}" = "10.96.79.110" ]
  [ "${lines[1]}" = "10.96.7.106" ]
  [ "${#lines[@]}" -eq 2 ]
}

@test "generate_ips: SET equals the known sandbox poll list (regression guard)" {
  # The 6 sandbox service ClusterIPs (discovered) + 2 static devices = the exact
  # set the hand-maintained sandbox ips.txt carried before this feature.
  make_discover 0 \
    10.96.79.110 10.96.97.124 10.96.7.106 10.96.45.60 10.96.134.213 10.96.221.219
  printf '192.168.50.230\n192.168.50.79\n' > "$STATIC_FILE"

  run bash "$GEN"
  [ "$status" -eq 0 ]

  expected="$BATS_TEST_TMPDIR/expected.txt"
  cat > "$expected" <<'EOF'
192.168.50.230
192.168.50.79
10.96.79.110
10.96.7.106
10.96.45.60
10.96.134.213
10.96.221.219
10.96.97.124
EOF
  # Compare as SETS (order-independent).
  run diff <(sort -u "$expected") <(sort -u "$IPS_FILE")
  [ "$status" -eq 0 ]
}
