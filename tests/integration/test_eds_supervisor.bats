#!/usr/bin/env bats
# tests/integration/test_eds_supervisor.bats
#
# End-to-end check (against the REAL sandbox eds pod) that the collector
# supervision works: the supervisor and collector are up, and killing the
# collector results in a respawn. SANDBOX ONLY (docker-desktop).

setup() {
  CTX="$(kubectl config current-context 2>/dev/null || true)"
  if [[ "$CTX" != "docker-desktop" ]]; then
    skip "not the sandbox (context=$CTX)"
  fi
  POD="$(kubectl get pods --no-headers --field-selector=status.phase=Running 2>/dev/null | awk '/^eds-deployment/{print $1; exit}')"
  if [[ -z "$POD" ]]; then
    skip "no running eds pod"
  fi
}

@test "eds supervisor: collector + supervisor are running" {
  run kubectl exec "$POD" -c eds -- bash -c 'pgrep -f supervise_eds.sh >/dev/null && echo SUP_UP; pgrep -x eds >/dev/null && echo EDS_UP'
  echo "$output"
  [[ "$output" == *"SUP_UP"* ]]
  [[ "$output" == *"EDS_UP"* ]]
}

@test "eds supervisor: killing the collector respawns it (new pid within ~10s)" {
  OLD=$(kubectl exec "$POD" -c eds -- pgrep -x eds | head -1)
  [ -n "$OLD" ]
  kubectl exec "$POD" -c eds -- bash -c "kill -9 $OLD"
  NEW=""
  for i in $(seq 1 12); do
    sleep 1
    NEW=$(kubectl exec "$POD" -c eds -- pgrep -x eds | head -1)
    [[ -n "$NEW" && "$NEW" != "$OLD" ]] && break
  done
  echo "old=$OLD new=$NEW"
  [ -n "$NEW" ]
  [ "$NEW" != "$OLD" ]
  # And exactly one collector (no duplicate supervisors spawning extras).
  run kubectl exec "$POD" -c eds -- bash -c 'pgrep -x eds | wc -l'
  [ "$output" -eq 1 ]
}
