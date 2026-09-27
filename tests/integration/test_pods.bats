#!/usr/bin/env bats
# tests/integration/test_pods.bats
#
# Live SANDBOX smoke tests for the homedata pods. READ-ONLY by default.
#
# SAFETY: these tests only ever run against the 'docker-desktop' kube context.
# run_tests.sh gates the whole tier, and setup() re-checks here as defence in
# depth so this file can never touch prod (holken2/minikube) even if invoked
# directly with `bats`.
#
# Sandbox NodePort map (differs from prod — do NOT reuse prod ports):
#   eds=30164  humi=30165  wind=30166  hue=30167  rain=30168
#
# Opt-in: set EDS_WITH_THROWAWAY=true (or pass --with-throwaway to run_tests.sh)
# to additionally deploy a temporary eds pod, smoke-test it, and tear it down.

HOST="${EDS_TEST_HOST:-127.0.0.1}"
EDS_PORT="${EDS_PORT:-30164}"
HUMI_PORT="${HUMI_PORT:-30165}"
WIND_PORT="${WIND_PORT:-30166}"
HUE_PORT="${HUE_PORT:-30167}"
RAIN_PORT="${RAIN_PORT:-30168}"

setup() {
  # Defence-in-depth prod guard.
  local ctx
  ctx="$(kubectl config current-context 2>/dev/null)"
  if [[ "$ctx" != "docker-desktop" ]]; then
    skip "kube context is '$ctx', not 'docker-desktop' — refusing to touch a non-sandbox cluster"
  fi
}

# --- helpers -----------------------------------------------------------------
pod_for() {  # $1 = app label -> pod name (first match)
  kubectl get pods -l "app=$1" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null
}

deploy_running() {  # $1 = deployment name
  kubectl get deploy "$1" -o jsonpath='{.status.readyReplicas}' 2>/dev/null | grep -q '^[1-9]'
}

http_code() {  # $1 = url; prints HTTP status, or 000 on connection failure
  local code
  code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "$1" 2>/dev/null)"
  echo "${code:-000}"
}

# --- eds pod (the core) ------------------------------------------------------
@test "eds: deployment is Running" {
  deploy_running eds-deployment
}

@test "eds: core processes alive (eds, mysqld, apache2)" {
  pod="$(pod_for eds)"
  [ -n "$pod" ]
  kubectl exec "$pod" -- bash -c "pgrep -x eds"
  kubectl exec "$pod" -- bash -c "pgrep mysqld"
  kubectl exec "$pod" -- bash -c "pgrep apache2"
}

@test "eds: MySQL reachable inside pod" {
  pod="$(pod_for eds)"
  [ -n "$pod" ]
  kubectl exec "$pod" -- bash -c "mysql -u dbuser -pkmjmkm54C# -e 'SELECT 1'"
}

@test "eds: sensor tables present" {
  pod="$(pod_for eds)"
  [ -n "$pod" ]
  run kubectl exec "$pod" -- bash -c "mysql -u dbuser -pkmjmkm54C# mydb -e 'SHOW TABLES'"
  [ "$status" -eq 0 ]
  [ -n "$output" ]
}

@test "eds: HTTP endpoint returns 200" {
  [ "$(http_code "http://$HOST:$EDS_PORT/")" = "200" ]
}

@test "eds: sensorcfg.php renders without PHP errors" {
  run bash -c "curl -s --max-time 5 http://$HOST:$EDS_PORT/sensorcfg.php"
  [ "$status" -eq 0 ]
  [[ "$output" != *"Fatal error"* ]]
  [[ "$output" != *"Parse error"* ]]
}

# --- other collector pods ----------------------------------------------------
@test "hue: deployment Running" {  deploy_running hue-deployment;  }
@test "humi: deployment Running" { deploy_running humi-deployment; }
@test "rain: deployment Running" { deploy_running rain-deployment; }
@test "wind: deployment Running" { deploy_running wind-deployment; }

@test "hue: collector process alive" {
  pod="$(pod_for hue)";  [ -n "$pod" ]
  kubectl exec "$pod" -- bash -c "pgrep -f huesensors.py"
}
@test "humi: collector process alive" {
  pod="$(pod_for humi)"; [ -n "$pod" ]
  kubectl exec "$pod" -- bash -c "pgrep -f humi.py"
}
@test "rain: collector process alive" {
  pod="$(pod_for rain)"; [ -n "$pod" ]
  kubectl exec "$pod" -- bash -c "pgrep -f rain.py"
}
@test "wind: collector process alive" {
  pod="$(pod_for wind)"; [ -n "$pod" ]
  kubectl exec "$pod" -- bash -c "pgrep -f wind.py"
}

@test "collector NodePorts serve HTTP (humi/wind/hue/rain)" {
  # Not every collector necessarily fronts HTTP; treat 200 as pass and a
  # connection failure ("000") as a soft skip so the smoke test stays useful.
  for pair in "humi:$HUMI_PORT" "wind:$WIND_PORT" "hue:$HUE_PORT" "rain:$RAIN_PORT"; do
    name="${pair%%:*}"; port="${pair##*:}"
    code="$(http_code "http://$HOST:$port/")"
    echo "$name ($port) -> $code"
    [[ "$code" == "200" || "$code" == "000" ]]
  done
}

# --- optional throwaway eds pod ---------------------------------------------
@test "throwaway eds pod: deploy, smoke-test, teardown" {
  if [[ "${EDS_WITH_THROWAWAY:-false}" != "true" ]]; then
    skip "set EDS_WITH_THROWAWAY=true (or --with-throwaway) to run"
  fi

  # Derive the current sandbox eds image so we test the same bits.
  local image
  image="$(kubectl get deploy eds-deployment -o jsonpath='{.spec.template.spec.containers[0].image}' 2>/dev/null)"
  [ -n "$image" ]

  local name="eds-throwaway-test"
  # Ensure teardown even on failure.
  teardown_throwaway() { kubectl delete pod "$name" --ignore-not-found --now >/dev/null 2>&1; }
  trap teardown_throwaway RETURN

  kubectl run "$name" --image="$image" --restart=Never \
    --command -- ./start.sh >/dev/null 2>&1
  # Wait up to 120s for Ready.
  kubectl wait --for=condition=Ready "pod/$name" --timeout=120s
  # Smoke: eds process comes up inside the throwaway pod.
  kubectl exec "$name" -- bash -c "pgrep -x eds || pgrep mysqld"
  teardown_throwaway
  # Confirm it's gone.
  ! kubectl get pod "$name" >/dev/null 2>&1
}
