#!/usr/bin/env bats
# tests/integration/test_type_case_normalization.bats
#
# Regression for the "Pris tile not rendered" fault: a sensorconfig row with a
# stray-cased type ("Price") must not break case-sensitive report branches.
# getSensorNames() (visualize/homeFunctions.php) is the single read source and
# now lowercases `type`, so all reports become case-insensitive on type.
#
# This runs the REAL homeFunctions.php via php8.3 inside the sandbox eds pod
# against a throwaway scratch database. SANDBOX ONLY; never touches mydb.

setup() {
  REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"

  CTX="$(kubectl config current-context 2>/dev/null || true)"
  if [[ "$CTX" != "docker-desktop" ]]; then
    skip "not the sandbox (context=$CTX)"
  fi
  POD="$(kubectl get pods --no-headers --field-selector=status.phase=Running 2>/dev/null | awk '/^eds-deployment/{print $1; exit}')"
  if [[ -z "$POD" ]]; then
    skip "no running eds pod in sandbox"
  fi
  DB="type_case_test"
}

teardown() {
  [[ -n "${POD:-}" ]] && kubectl exec "$POD" -c eds -- bash -c "mysql -e 'DROP DATABASE IF EXISTS $DB;' 2>/dev/null; rm -f /tmp/type_case_probe.php" || true
}

@test "getSensorNames lowercases stray-cased type (Price -> price)" {
  # Ship the CURRENT homeFunctions.php into the pod so we test the repo version.
  kubectl cp "$REPO_ROOT/visualize/homeFunctions.php" "$POD:/tmp/homeFunctions_test.php" -c eds

  # Seed a scratch DB with a deliberately mis-cased type.
  kubectl exec "$POD" -c eds -- bash -c "
    mysql <<SQL
DROP DATABASE IF EXISTS $DB;
CREATE DATABASE $DB; USE $DB;
CREATE TABLE sensorconfig (id int NOT NULL AUTO_INCREMENT, sensorid text NOT NULL, sensorname text NOT NULL, color text NOT NULL, visible text NOT NULL, type text NOT NULL, PRIMARY KEY(id));
INSERT INTO sensorconfig (sensorid,sensorname,color,visible,type) VALUES
 ('7367161076056225378','Pris','black','True','Price'),
 ('16261054761367928446','Ute','blue','True','TEMP');
SQL
  "

  # Probe that calls the real getSensorNames() and prints the normalized types.
  cat > /tmp/type_case_probe.php <<'PHP'
<?php
require_once('/tmp/homeFunctions_test.php');
$s = getSensorNames('dbuser','kmjmkm54C#','type_case_test','127.0.0.1');
$colName = 1; $colType = 4;
foreach ($s[$colName] as $k => $name) {
  echo $name . '=' . $s[$colType][$k] . "\n";
}
PHP
  kubectl cp /tmp/type_case_probe.php "$POD:/tmp/type_case_probe.php" -c eds

  run kubectl exec "$POD" -c eds -- php /tmp/type_case_probe.php
  echo "$output"
  [ "$status" -eq 0 ]
  # Types must come back lowercased regardless of stored case.
  [[ "$output" == *"Pris=price"* ]]
  [[ "$output" == *"Ute=temp"* ]]
  # And must NOT retain the stray casing.
  [[ "$output" != *"Price"* ]]
  [[ "$output" != *"TEMP"* ]]
}
