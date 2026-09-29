#!/usr/bin/env bats
# tests/integration/test_sensorconfig_invariant.bats
#
# End-to-end proof (against the REAL MySQL in the sandbox eds pod) of the core
# invariant:
#
#   A sensorconfig row whose sensorname is NOT 'name' is never overwritten or
#   demoted by the reconcile logic (migration + fill-only SEED merge), while
#   placeholder rows ARE filled and duplicate sensorids ARE de-duplicated.
#
# SAFETY: SANDBOX ONLY. This test refuses to run unless the current kube context
# is docker-desktop, and it operates in a throwaway scratch database
# (sc_invariant_test) — it never touches mydb.

setup() {
  REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"

  CTX="$(kubectl config current-context 2>/dev/null || true)"
  if [[ "$CTX" != "docker-desktop" ]]; then
    skip "not the sandbox (context=$CTX); refusing to run against non-sandbox"
  fi

  POD="$(kubectl get pods --no-headers 2>/dev/null | awk '/^eds-deployment/{print $1; exit}')"
  if [[ -z "$POD" ]]; then
    skip "no eds pod found in sandbox"
  fi
  DB="sc_invariant_test"
}

teardown() {
  [[ -n "${POD:-}" ]] && kubectl exec "$POD" -c eds -- mysql -e "DROP DATABASE IF EXISTS $DB;" 2>/dev/null || true
}

@test "invariant: named rows survive, placeholders fill, duplicates de-dup (real MySQL)" {
  # Copy the real migration into the pod.
  kubectl cp "$REPO_ROOT/container/migrate_sensorconfig_unique.sql" "$POD:/tmp/mig_test.sql" -c eds

  run kubectl exec "$POD" -c eds -- bash -c '
    set -e
    DB="'"$DB"'"
    mysql <<SQL
DROP DATABASE IF EXISTS $DB;
CREATE DATABASE $DB; USE $DB;
CREATE TABLE sensorconfig (id int NOT NULL AUTO_INCREMENT, sensorid text NOT NULL, sensorname text NOT NULL, color text NOT NULL, visible text NOT NULL, type text NOT NULL, PRIMARY KEY(id));
INSERT INTO sensorconfig (sensorid,sensorname,color,visible,type) VALUES
 ("16261054761367928446","Ute","blue","True","temp"),
 ("16261054761367928446","name","black","false","default"),
 ("10631191365266147712","name","black","false","default"),
 ("555","MyCustom","red","True","temp");
SQL

    sed "s/^USE mydb;/USE $DB;/; s/table_schema = .mydb./table_schema = \"'"$DB"'\"/" /tmp/mig_test.sql | mysql

    # --- Stage 1: merge a POLLUTED prod-style (INSERT-only) PVC dump fill-only.
    # The dump tries to reset Ute AND MyCustom back to placeholders; both must
    # survive. This exercises the exact staging + sed + merge path from
    # createSensorConfig.sh step 3.
    mysql $DB -e "DROP TABLE IF EXISTS sensorconfig_stage; CREATE TABLE sensorconfig_stage (id int NOT NULL AUTO_INCREMENT, sensorid text NOT NULL, sensorname text NOT NULL, color text NOT NULL, visible text NOT NULL, type text NOT NULL, PRIMARY KEY(id));"
    DUMPF="$(mktemp)"
    cat > "$DUMPF" <<DUMP
INSERT INTO \`sensorconfig\` VALUES (1,"16261054761367928446","name","black","false","default"),(2,"555","name","black","false","default");
DUMP
    sed -e "s/\`sensorconfig\`/\`sensorconfig_stage\`/g" -e "s/ sensorconfig / sensorconfig_stage /g" "$DUMPF" \
      | sed -e "s/^DROP TABLE[^;]*;/-- (neutralised DROP)/I" \
      | mysql $DB
    rm -f "$DUMPF"
    mysql $DB <<SQL
INSERT INTO sensorconfig (sensorid, sensorname, color, visible, type)
SELECT s.sensorid,s.sensorname,s.color,s.visible,s.type FROM sensorconfig_stage s
ON DUPLICATE KEY UPDATE
  color      = IF(sensorconfig.sensorname = "name", VALUES(color),      sensorconfig.color),
  visible    = IF(sensorconfig.sensorname = "name", VALUES(visible),    sensorconfig.visible),
  type       = IF(sensorconfig.sensorname = "name", VALUES(type),       sensorconfig.type),
  sensorname = IF(sensorconfig.sensorname = "name", VALUES(sensorname), sensorconfig.sensorname);
DROP TABLE sensorconfig_stage;
SQL

    # --- Stage 2: fill-only SEED merge (Ute seed has DIFFERENT values, El fills).
    mysql $DB <<SQL
CREATE TABLE seed (sensorid text,sensorname text,color text,visible text,type text);
INSERT INTO seed VALUES
 ("16261054761367928446","UteSEED","green","False","OTHER"),
 ("10631191365266147712","El","black","True","power"),
 ("999","Newby","black","True","temp");
INSERT INTO sensorconfig (sensorid, sensorname, color, visible, type)
SELECT s.sensorid,s.sensorname,s.color,s.visible,s.type FROM seed s
ON DUPLICATE KEY UPDATE
  color      = IF(sensorconfig.sensorname = "name", VALUES(color),      sensorconfig.color),
  visible    = IF(sensorconfig.sensorname = "name", VALUES(visible),    sensorconfig.visible),
  type       = IF(sensorconfig.sensorname = "name", VALUES(type),       sensorconfig.type),
  sensorname = IF(sensorconfig.sensorname = "name", VALUES(sensorname), sensorconfig.sensorname);
SQL

    echo "RESULT:"
    mysql -N $DB -e "SELECT sensorid,sensorname,color,visible,type FROM sensorconfig ORDER BY sensorid;"
    echo "DUPCHECK:"
    mysql -N $DB -e "SELECT COUNT(*) FROM (SELECT sensorid FROM sensorconfig GROUP BY sensorid HAVING COUNT(*)>1) d;"
  '
  echo "$output"
  [ "$status" -eq 0 ]

  # Ute stayed fully protected through BOTH a polluted dump and a divergent SEED.
  [[ "$output" == *"16261054761367928446	Ute	blue	True	temp"* ]]
  # El placeholder filled from SEED.
  [[ "$output" == *"10631191365266147712	El	black	True	power"* ]]
  # User-named custom sensor survived the polluted dump's demotion attempt.
  [[ "$output" == *"555	MyCustom	red	True	temp"* ]]
  # Missing sensor inserted by SEED.
  [[ "$output" == *"999	Newby	black	True	temp"* ]]
  # No duplicate sensorids remain.
  [[ "$output" == *"DUPCHECK:"$'\n'"0"* ]]
}
