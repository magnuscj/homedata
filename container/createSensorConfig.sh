#!/bin/bash
# Create / reconcile sensorconfig (6-column: id, sensorid, sensorname, color,
# visible, type). sensorid = deterministic FNV-1a id (canonical, portable).
#
# INVARIANT (the whole point of this script):
#   An existing row whose sensorname is NOT the placeholder 'name' must NEVER be
#   overwritten or demoted here. We only ever (a) create missing rows and
#   (b) fill placeholder rows. Real, user-assigned names survive every restart,
#   redeploy, and reconcile — no matter what the persisted PVC dump contains.
#
# Reconcile order (all non-destructive to named rows):
#   1. Ensure the table exists (CREATE TABLE IF NOT EXISTS — never DROP; the DB
#      lives on a Retain PVC and is the source of truth for live names).
#   2. Enforce UNIQUE(sensorid) via the idempotent migration, so the merges
#      below can use INSERT ... ON DUPLICATE KEY UPDATE race-free.
#   3. Merge the persisted PVC dump ($STORAGE_DIR/sensorconfig.sql), if present,
#      into a staging table and fill-only (never clobber a live named row).
#   4. Merge the hardcoded SEED the same fill-only way, so known sensors are
#      always named even if the PVC dump was polluted / missing / stale.

set -uo pipefail

# Storage location for the persisted sensorconfig dump. Overridable for tests;
# defaults to the in-pod PVC mount so production behaviour is unchanged.
STORAGE_DIR="${STORAGE_DIR:-/usr/storage}"

# Repo dir that holds the migration SQL. Overridable for tests.
SCRIPT_DIR="${SCRIPT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
MIGRATION_SQL="$SCRIPT_DIR/migrate_sensorconfig_unique.sql"

# ---------------------------------------------------------------------------
# 1. Ensure the table exists. NEVER drop it: the live table is authoritative
#    for real names. A fresh/empty PVC just yields an empty table here.
# ---------------------------------------------------------------------------
mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS mydb;
USE mydb;
CREATE TABLE IF NOT EXISTS sensorconfig (
  id int NOT NULL AUTO_INCREMENT,
  sensorid text NOT NULL,
  sensorname text NOT NULL,
  color text NOT NULL,
  visible text NOT NULL,
  type text NOT NULL,
  PRIMARY KEY (id)
);
SQL

# ---------------------------------------------------------------------------
# 2. Enforce UNIQUE(sensorid) (de-dups first, keeping named rows). Idempotent.
# ---------------------------------------------------------------------------
if [[ -f "$MIGRATION_SQL" ]]; then
  echo "Applying sensorid uniqueness migration"
  mysql < "$MIGRATION_SQL"
else
  echo "WARN: migration SQL not found at $MIGRATION_SQL; continuing without UNIQUE(sensorid)" >&2
fi

# Helper: merge a source table into sensorconfig FILL-ONLY.
#   * insert rows whose sensorid is missing;
#   * for an existing row, overwrite ONLY if the live row is a placeholder
#     (sensorname = 'name'). A live real name is left untouched.
# Requires UNIQUE(sensorid). If the constraint is absent (migration missing),
# we degrade to a plain insert-if-missing via NOT EXISTS so we still never
# demote a named row.
merge_fill_only() {
  local src="$1"   # source table name (already in mydb)
  # NOTE on ordering: in MySQL's ON DUPLICATE KEY UPDATE, assignments evaluate
  # left-to-right and later expressions see columns ALREADY updated earlier in
  # the same row. So the discriminator column (sensorname) MUST be assigned
  # LAST; otherwise the color/visible/type guards would test the post-update
  # sensorname and never fire. All guards therefore key on sensorconfig.sensorname
  # while it still holds its pre-update ('name' or a real name) value.
  mysql mydb <<SQL
INSERT INTO sensorconfig (sensorid, sensorname, color, visible, type)
SELECT s.sensorid, s.sensorname, s.color, s.visible, s.type
FROM \`$src\` s
ON DUPLICATE KEY UPDATE
  color      = IF(sensorconfig.sensorname = 'name', VALUES(color),      sensorconfig.color),
  visible    = IF(sensorconfig.sensorname = 'name', VALUES(visible),    sensorconfig.visible),
  type       = IF(sensorconfig.sensorname = 'name', VALUES(type),       sensorconfig.type),
  sensorname = IF(sensorconfig.sensorname = 'name', VALUES(sensorname), sensorconfig.sensorname);
SQL
}

# ---------------------------------------------------------------------------
# 3. Merge the persisted PVC dump, if any, via a staging table (fill-only).
#    We deliberately do NOT pipe the dump straight into mydb: historically the
#    dump did `DROP TABLE sensorconfig` + reinserted a possibly-polluted set,
#    which is exactly how real names got demoted. Staging + fill-only merge
#    makes the dump additive, never destructive.
# ---------------------------------------------------------------------------
if [[ -f "$STORAGE_DIR/sensorconfig.sql" ]]; then
  echo "Merging $STORAGE_DIR/sensorconfig.sql (fill-only)"
  # Pre-create an empty staging table shaped like the live one (without the
  # unique key, so a polluted dump with duplicate sensorids still loads; the
  # fill-only merge is what enforces the invariant afterwards).
  mysql mydb <<'SQL'
DROP TABLE IF EXISTS sensorconfig_stage;
CREATE TABLE sensorconfig_stage (
  id int NOT NULL AUTO_INCREMENT,
  sensorid text NOT NULL,
  sensorname text NOT NULL,
  color text NOT NULL,
  visible text NOT NULL,
  type text NOT NULL,
  PRIMARY KEY (id)
);
SQL
  # Load the dump's rows into the staging table. Production dumps are produced
  # with `mysqldump --no-create-info` (INSERT-only), but be robust to full
  # dumps too: retarget the table name to the staging table, and neutralise any
  # DROP/CREATE so they can never touch the live table or collide with the
  # pre-created staging table.
  sed -e 's/`sensorconfig`/`sensorconfig_stage`/g' \
      -e 's/ sensorconfig / sensorconfig_stage /g' \
      "$STORAGE_DIR/sensorconfig.sql" \
    | sed -e 's/^DROP TABLE[^;]*;/-- (neutralised DROP)/I' \
          -e 's/^CREATE TABLE `sensorconfig_stage`/CREATE TABLE IF NOT EXISTS `sensorconfig_stage_unused`/I' \
    | mysql mydb 2>/dev/null
  merge_fill_only sensorconfig_stage
  mysql mydb -e "DROP TABLE IF EXISTS sensorconfig_stage; DROP TABLE IF EXISTS sensorconfig_stage_unused;" 2>/dev/null
fi

# ---------------------------------------------------------------------------
# 4. Merge the hardcoded SEED (fill-only) so known sensors are always named,
#    regardless of what the PVC dump held. SEED is authoritative ONLY for rows
#    that are missing or still placeholders — it never demotes a live name.
# ---------------------------------------------------------------------------
echo "Merging hardcoded SEED (fill-only)"
mysql mydb <<'SQL'
DROP TABLE IF EXISTS sensorconfig_seed;
CREATE TABLE sensorconfig_seed (
  sensorid text NOT NULL,
  sensorname text NOT NULL,
  color text NOT NULL,
  visible text NOT NULL,
  type text NOT NULL
);
INSERT INTO sensorconfig_seed (sensorid,sensorname,color,visible,type) VALUES
('16261054761367928446','Ute','blue','True','temp'),
('11311998247970688570','Fry_gr','darkorchid','True','temp'),
('9095587817331319166','Inne','green','True','temp'),
('10631191365266147712','El','black','True','power'),
('213136193804146860','Garage','black','True','temp'),
('12837279452181235050','Heater','black','True','power'),
('7851977845202606380','Skorst','red','True','temp'),
('702045547157631543','Sovrum','cadetblue4','True','temp'),
('11440049530512997765','Tryck','black','false','bar'),
('10832894972928239946','Fukt','black','True','moisture'),
('2705388970248215848','Kontor','black','false','temp'),
('12580286349677670678','FuktKon','black','false','moisture'),
('15555407530156859778','WiSpeed','black','false','Wind'),
('14287746912078928553','WiSMax','black','false','Wind'),
('12805902924744758861','WiSDir','black','false','Wind'),
('3142761097160776728','Fry_ko','deepskyblue3','True','temp'),
('16287663478246657457','Kyl_ko','deepskyblue1','True','temp'),
('1300729859917990278','Kyl_gr','darkorchid4','True','temp'),
('9276034250560746343','vaxthus','black','True','temp'),
('15050001762446072976','back___8','black','false','soilmoist'),
('8035609657829439818','back___3','black','false','soilmoist'),
('11417017316545152923','back___4','black','false','soilmoist'),
('15716539391930188350','back___6','black','false','soilmoist'),
('5297168414477736841','back___7','black','false','soilmoist'),
('8192219494370953084','back___1','black','false','soilmoist'),
('15384184899465171632','Palett_5','black','false','soilmoist'),
('15741507826216356597','Fl.Lisa2','black','false','soilmoist'),
('2140482302588567291','Fry_ga','white','True','temp'),
('10936074920700898100','Regn','royalblue4','True','rain'),
('18432709685998635173','regnW','white','True','temp'),
('14748571628222628275','regnM','white','True','temp'),
('7367161076056225378','Pris','black','True','price');
SQL
merge_fill_only sensorconfig_seed
mysql mydb -e "DROP TABLE IF EXISTS sensorconfig_seed;" 2>/dev/null

echo "sensorconfig reconcile complete"
