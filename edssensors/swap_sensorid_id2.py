#!/usr/bin/env python3
"""
swap_sensorid_id2.py — Promote the deterministic FNV-1a id (currently stored in
`sensorconfig.id2`) to be the canonical `sensorid`, demoting the legacy std::hash
id into `id2`. Also rewrites the `sensorid` values in every monthly measurement
table so config and readings stay joined.

WHAT IT DOES (in order)
    1. Reads the legacy->id2 mapping straight from `sensorconfig` (only rows whose
       id2 is populated). This mapping is the source of truth for the rewrite; it
       is captured BEFORE any mutation.
    2. Snapshots that mapping into `sensor_id_swap_map` (audit / rollback aid).
    3. For every monthly table (SHOW TABLES LIKE 'table20%'):
         UPDATE <t> SET sensorid = <id2> WHERE sensorid = <legacy>;
       Only mapped legacy ids are touched. The `electricityprice` sentinel and any
       measurement sensorid without a config row are left untouched.
    4. Swaps the sensorconfig columns in a single statement (uses pre-update RHS):
         UPDATE sensorconfig SET sensorid = id2, id2 = sensorid
         WHERE id2 IS NOT NULL AND id2 <> '';
       Result: sensorid = FNV-1a, id2 = legacy std::hash (kept as back-reference).
    5. Records a tag in `sensor_id_migration_log` so a second --apply is a no-op.

SCHEMES / SAFETY
    * sensorid has NO unique constraint (PK is `id`), so rewriting legacy->id2 in a
      table that already contains id2 rows (table202609) simply MERGES the split
      history of a sensor under one id — the desired end state.
    * Idempotent: guarded by the migration-log tag; re-running does nothing.
    * Steps 3 + 4 run inside ONE transaction. If anything fails it rolls back.
    * Dry-run by default: prints the plan + before/after counts, changes nothing.
      Pass --apply to execute.

USAGE (runs the mysql CLI; intended to run inside the eds pod)
    python3 swap_sensorid_id2.py            # dry run
    python3 swap_sensorid_id2.py --apply    # execute
    python3 swap_sensorid_id2.py --db mydb --user dbuser --pwd '...' --apply
"""
import argparse
import subprocess
import sys

TAG = "swap_sensorid_id2_v1"
SENTINEL = "electricityprice"


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


def already_applied(args):
    rows = mysql(args,
                 "SELECT COUNT(*) FROM sensor_id_migration_log WHERE tag='%s'" % TAG)
    # table may not exist yet -> mysql() would have errored; create defensively
    return rows and rows[0][0] not in ("", "0")


def ensure_log_table(args):
    mysql(args,
          "CREATE TABLE IF NOT EXISTS sensor_id_migration_log "
          "(tag VARCHAR(64) NOT NULL PRIMARY KEY, "
          " applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)",
          want_rows=False)


def monthly_tables(args):
    rows = mysql(args, "SHOW TABLES LIKE 'table20%'")
    return [r[0] for r in rows]


def load_mapping(args):
    """legacy sensorid -> id2, only populated id2, excluding the sentinel."""
    rows = mysql(args,
                 "SELECT sensorid, id2, sensorname FROM sensorconfig "
                 "WHERE id2 IS NOT NULL AND id2 <> '' AND sensorid <> '%s'" % SENTINEL)
    return [(r[0], r[1], r[2] if len(r) > 2 else "") for r in rows]


def count(args, table, where):
    rows = mysql(args, "SELECT COUNT(*) FROM %s WHERE %s" % (table, where))
    return int(rows[0][0]) if rows else 0


def sql_escape(v):
    return v.replace("\\", "\\\\").replace("'", "\\'")


def main():
    ap = argparse.ArgumentParser(description="Swap sensorconfig.sensorid <-> id2 and rewrite measurement tables.")
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--user", default="dbuser")
    ap.add_argument("--pwd", default="kmjmkm54C#")
    ap.add_argument("--db", default="mydb")
    ap.add_argument("--apply", action="store_true", help="Execute. Without it, dry run.")
    args = ap.parse_args()

    ensure_log_table(args)
    if already_applied(args):
        print("Migration tag '%s' already present — nothing to do (idempotent)." % TAG)
        return

    mapping = load_mapping(args)
    tables = monthly_tables(args)

    print("=== swap plan ===")
    print("db                 : %s" % args.db)
    print("monthly tables     : %s" % ", ".join(tables) if tables else "(none)")
    print("mapped sensors     : %d  (config rows with populated id2, excl. %s)"
          % (len(mapping), SENTINEL))

    # sanity: id2 targets must be unique
    id2s = [m[1] for m in mapping]
    dup = set([x for x in id2s if id2s.count(x) > 1])
    if dup:
        sys.exit("ABORT: duplicate id2 targets in mapping: %s" % ", ".join(dup))

    # per-table impact preview
    total_before = {}
    for t in tables:
        total_before[t] = count(args, t, "1=1")
        rewriteable = 0
        for legacy, id2, name in mapping:
            rewriteable += count(args, t, "sensorid='%s'" % sql_escape(legacy))
        elp = count(args, t, "sensorid='%s'" % SENTINEL)
        distinct_before = count(args,
                                "(SELECT DISTINCT sensorid FROM %s) x" % t, "1=1")
        print("  %-14s rows=%-7d rewriteable_rows=%-6d electricityprice=%-5d distinct_ids=%d"
              % (t, total_before[t], rewriteable, elp, distinct_before))

    print("\n-- measurement rewrites (per mapped sensor, per table) --")
    for t in tables:
        for legacy, id2, name in mapping:
            n = count(args, t, "sensorid='%s'" % sql_escape(legacy))
            if n:
                print("UPDATE %s SET sensorid='%s' WHERE sensorid='%s';  -- %s (%d rows)"
                      % (t, id2, legacy, name, n))

    print("\n-- sensorconfig column swap (order-independent, via snapshot map) --")
    print("UPDATE sensorconfig c JOIN sensor_id_swap_map s ON c.sensorid=s.legacy_sensorid "
          "SET c.sensorid=s.new_sensorid, c.id2=s.legacy_sensorid;  -- %d rows" % len(mapping))

    if not args.apply:
        print("\n(dry run — pass --apply to execute)")
        return

    # ---- apply ----
    stmts = ["START TRANSACTION;"]
    # snapshot mapping for audit/rollback
    stmts.append("CREATE TABLE IF NOT EXISTS sensor_id_swap_map "
                 "(legacy_sensorid VARCHAR(64), new_sensorid VARCHAR(64), "
                 " sensorname VARCHAR(128), captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);")
    for legacy, id2, name in mapping:
        stmts.append("INSERT INTO sensor_id_swap_map (legacy_sensorid,new_sensorid,sensorname) "
                     "VALUES ('%s','%s','%s');"
                     % (sql_escape(legacy), sql_escape(id2), sql_escape(name)))
    # measurement rewrites
    for t in tables:
        for legacy, id2, name in mapping:
            stmts.append("UPDATE %s SET sensorid='%s' WHERE sensorid='%s';"
                         % (t, id2, sql_escape(legacy)))
    # column swap — order-independent via the snapshot map captured above.
    # (A single-table "SET sensorid=id2, id2=sensorid" is UNSAFE: MySQL evaluates
    #  SET assignments left-to-right, so id2 would read the already-updated sensorid.)
    stmts.append("UPDATE sensorconfig c JOIN sensor_id_swap_map s "
                 "ON c.sensorid = s.legacy_sensorid "
                 "SET c.sensorid = s.new_sensorid, c.id2 = s.legacy_sensorid;")
    # migration tag
    stmts.append("INSERT INTO sensor_id_migration_log (tag) VALUES ('%s');" % TAG)
    stmts.append("COMMIT;")

    print("\nApplying %d statements in one transaction..." % len(stmts))
    mysql(args, "\n".join(stmts), want_rows=False)

    # ---- verify ----
    print("=== post-apply verification ===")
    for t in tables:
        after = count(args, t, "1=1")
        elp = count(args, t, "sensorid='%s'" % SENTINEL)
        distinct_after = count(args, "(SELECT DISTINCT sensorid FROM %s) x" % t, "1=1")
        flag = "OK" if after == total_before[t] else "!! ROW COUNT CHANGED"
        print("  %-14s rows=%d (before %d) %s  electricityprice=%d distinct_ids=%d"
              % (t, after, total_before[t], flag, elp, distinct_after))
    sc = mysql(args, "SELECT COUNT(*), SUM(sensorid REGEXP '^[0-9]+$'), "
                     "SUM(id2 IS NOT NULL AND id2<>'') FROM sensorconfig")
    print("  sensorconfig: total=%s numeric_sensorid=%s with_id2(legacy)=%s"
          % tuple(sc[0]))
    print("done.")


if __name__ == "__main__":
    main()
