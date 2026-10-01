#!/usr/bin/env python3
import os
"""
repair_sensorconfig_dupes.py — CONFIG-ONLY repair for the duplicated
`sensorconfig` state seen after deploying the FNV-1a (stableHash) eds binary
against a *pre-swap* config.

PROBLEM
    Named config rows were pre-swap oriented (sensorid = legacy std::hash,
    id2 = FNV-1a). When the FNV-emitting binary went live it looked sensors up
    by the FNV id, found nothing (named rows hold the legacy id in `sensorid`),
    and INSERTed brand-new placeholder rows (sensorname='name', type='default')
    keyed on the FNV id. Result: every real sensor has TWO rows —
        named:       (sensorid=legacy, id2=FNV,   name=<real>)
        placeholder: (sensorid=FNV,    id2=legacy, name='name')
    and the live status page shows 'name' for everything because the FNV lookup
    hits the placeholder, not the named row.

FIX (config only; measurement re-keying is a SEPARATE step, intentionally)
    For each named row n that has an inverse placeholder partner p
    (p.sensorid = n.id2 AND p.id2 = n.sensorid, p.sensorname='name'):
        1. UPDATE n: sensorid <- n.id2 (FNV), id2 <- n.sensorid (legacy).
           Name/color/visible/type stay on n (nothing user-edited is lost),
           and the numeric PK (n.id) is preserved.
        2. DELETE the placeholder partner p.
    Rows NOT touched:
        * named rows with id2 IS NULL  (e.g. Garage/Sovrum/... — left as-is by
          explicit decision; handle via add_id2.py later if needed)
        * placeholder 'name' rows that have NO named partner (genuinely unnamed
          sensors — eds would just recreate them)

SAFETY
    * Dry-run by default; prints the exact per-row plan. Pass --apply to execute.
    * Snapshots every affected row into sensorconfig_dupe_repair_backup first.
    * Update + delete run in ONE transaction.
    * Idempotent: guarded by a tag in sensor_id_migration_log; re-running is a
      no-op. Also, only rows that still have a placeholder partner are acted on.

USAGE
    python3 repair_sensorconfig_dupes.py            # dry run
    python3 repair_sensorconfig_dupes.py --apply    # execute
"""
import argparse
import subprocess
import sys

TAG = "sensorconfig_dupe_repair_v1"


def mysql(args, sql, want_rows=True):
    cmd = ["mysql", "-h", args.host, "-u", args.user, "-p" + args.pwd,
           "-N", "-B", "-e", sql, args.db]
    r = subprocess.run(cmd, capture_output=True)
    if r.returncode != 0:
        sys.stderr.write("mysql error:\n" + r.stderr.decode("utf-8", "replace"))
        sys.exit(2)
    if not want_rows:
        return None
    out = r.stdout.decode("utf-8", "replace").splitlines()
    return [line.split("\t") for line in out if line != ""]


def sql_escape(v):
    return v.replace("\\", "\\\\").replace("'", "\\'")


def ensure_log_table(args):
    mysql(args,
          "CREATE TABLE IF NOT EXISTS sensor_id_migration_log "
          "(tag VARCHAR(64) NOT NULL PRIMARY KEY, "
          " applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)",
          want_rows=False)


def already_applied(args):
    rows = mysql(args,
                 "SELECT COUNT(*) FROM sensor_id_migration_log WHERE tag='%s'" % TAG)
    return rows and rows[0][0] not in ("", "0")


def load_pairs(args):
    """Return list of (n_id, n_sensorid_legacy, n_id2_fnv, n_name, p_id) for each
    named row that has an inverse placeholder partner."""
    rows = mysql(args,
                 "SELECT n.id, n.sensorid, n.id2, n.sensorname, p.id "
                 "FROM sensorconfig n "
                 "JOIN sensorconfig p "
                 "  ON p.sensorid = n.id2 AND p.id2 = n.sensorid "
                 "WHERE n.sensorname <> 'name' "
                 "  AND p.sensorname = 'name' "
                 "  AND n.id2 IS NOT NULL AND n.id2 <> ''")
    return [(r[0], r[1], r[2], r[3], r[4]) for r in rows]


def main():
    ap = argparse.ArgumentParser(description="Config-only repair of duplicated sensorconfig rows.")
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--user", default="dbuser")
    ap.add_argument("--pwd", default=os.environ.get("DB_PASSWORD", ""))
    ap.add_argument("--db", default="mydb")
    ap.add_argument("--apply", action="store_true", help="Execute. Without it, dry run.")
    args = ap.parse_args()

    ensure_log_table(args)
    if already_applied(args):
        print("Repair tag '%s' already present — nothing to do (idempotent)." % TAG)
        return

    pairs = load_pairs(args)

    print("=== sensorconfig dupe-repair plan ===")
    print("db                     : %s" % args.db)
    print("named<->placeholder pairs to repair : %d" % len(pairs))

    # Safety: each named row's target FNV id (n.id2) must be unique, and the
    # placeholder ids must be unique, so nothing overwrites another sensor.
    fnv_targets = [p[2] for p in pairs]
    dup_fnv = sorted({x for x in fnv_targets if fnv_targets.count(x) > 1})
    if dup_fnv:
        print("ABORT: duplicate FNV targets among named rows: %s" % ", ".join(dup_fnv))
        sys.exit(2)
    p_ids = [p[4] for p in pairs]
    dup_p = sorted({x for x in p_ids if p_ids.count(x) > 1})
    if dup_p:
        print("ABORT: a placeholder row matched multiple named rows: ids %s" % ", ".join(dup_p))
        sys.exit(2)

    print("\n-- per sensor: update named row to FNV key, delete placeholder --")
    for n_id, legacy, fnv, name, p_id in pairs:
        print("  [%-10s] UPDATE sensorconfig id=%s SET sensorid=%s (FNV), id2=%s (legacy); "
              "DELETE placeholder id=%s"
              % (name, n_id, fnv, legacy, p_id))

    if not args.apply:
        print("\n(dry run — pass --apply to execute)")
        return

    # ---- apply ----
    stmts = ["START TRANSACTION;"]
    # snapshot every affected row (named + placeholder) for rollback/audit
    stmts.append("CREATE TABLE IF NOT EXISTS sensorconfig_dupe_repair_backup "
                 "(id INT, sensorid TEXT, id2 TEXT, sensorname TEXT, color TEXT, "
                 " visible TEXT, type TEXT, role VARCHAR(16), "
                 " captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);")
    for n_id, legacy, fnv, name, p_id in pairs:
        stmts.append("INSERT INTO sensorconfig_dupe_repair_backup "
                     "(id,sensorid,id2,sensorname,color,visible,type,role) "
                     "SELECT id,sensorid,id2,sensorname,color,visible,type,'named' "
                     "FROM sensorconfig WHERE id=%s;" % n_id)
        stmts.append("INSERT INTO sensorconfig_dupe_repair_backup "
                     "(id,sensorid,id2,sensorname,color,visible,type,role) "
                     "SELECT id,sensorid,id2,sensorname,color,visible,type,'placeholder' "
                     "FROM sensorconfig WHERE id=%s;" % p_id)
    # update named rows to FNV key, then delete the placeholder partners
    for n_id, legacy, fnv, name, p_id in pairs:
        stmts.append("UPDATE sensorconfig SET sensorid='%s', id2='%s' WHERE id=%s;"
                     % (sql_escape(fnv), sql_escape(legacy), n_id))
        stmts.append("DELETE FROM sensorconfig WHERE id=%s;" % p_id)
    stmts.append("INSERT INTO sensor_id_migration_log (tag) VALUES ('%s');" % TAG)
    stmts.append("COMMIT;")

    print("\nApplying %d statements in one transaction..." % len(stmts))
    mysql(args, "\n".join(stmts), want_rows=False)

    # ---- verify ----
    print("=== post-apply verification ===")
    total = mysql(args, "SELECT COUNT(*) FROM sensorconfig")[0][0]
    named = mysql(args, "SELECT COUNT(*) FROM sensorconfig WHERE sensorname<>'name'")[0][0]
    print("sensorconfig rows now: %s (named=%s)" % (total, named))
    # every repaired sensor: named row should now be FNV-keyed and have no placeholder
    remaining = mysql(args,
                      "SELECT COUNT(*) FROM sensorconfig n JOIN sensorconfig p "
                      "ON p.sensorid=n.id2 AND p.id2=n.sensorid "
                      "WHERE n.sensorname<>'name' AND p.sensorname='name'")[0][0]
    print("named rows still having a placeholder partner (should be 0): %s" % remaining)
    print("done.")


if __name__ == "__main__":
    main()
