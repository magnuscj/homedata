#!/usr/bin/env python3
import os
"""
rekey_measurements.py — Re-key measurement tables from the legacy std::hash id
to the canonical FNV-1a id, using the (already-repaired, post-swap) sensorconfig
as the source of truth.

STATE ASSUMED (post config repair)
    sensorconfig.sensorid = FNV-1a (canonical)   <- eds now emits & looks up this
    sensorconfig.id2      = legacy std::hash

DIRECTION
    For each config row:  measurement.sensorid == id2 (legacy)  ->  sensorid (FNV)
    (This is the REVERSE of swap_sensorid_id2.py, which was written for the
     pre-swap state; do not use that script here.)

COLLISION SAFETY
    The 44 unnamed placeholder rows are themselves doubled: many exist as mutual
    inverse pairs (row A: sensorid=X,id2=Y ; row B: sensorid=Y,id2=X). For those,
    there is NO unambiguous legacy->FNV direction, and rewriting them would chain
    (a target of one UPDATE is the source of another) and corrupt data.
    We therefore rewrite ONLY unambiguous mappings:
      * drop any mapping whose FNV target is also used as a legacy source
        (inverse/chain set) — these are reported and left untouched,
      * abort if, after filtering, any residual chain remains.
    Named/repaired sensors are unambiguous and are re-keyed normally.

SCOPE
    --only-tables t1,t2,...   restrict to specific monthly tables (e.g. re-key
    older months now and handle September separately). Default: all table20%.

SAFETY
    Dry-run by default (prints per-table, per-sensor UPDATE plan + counts).
    --apply runs all rewrites for the in-scope tables in ONE transaction with
    before/after row-count verification. sensorid has no unique constraint, so
    merging split history under one id is fine and row counts must be unchanged.
"""
import argparse
import subprocess
import sys

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


def sql_escape(v):
    return v.replace("\\", "\\\\").replace("'", "\\'")


def all_monthly_tables(args):
    rows = mysql(args, "SHOW TABLES LIKE 'table20%'")
    return [r[0] for r in rows]


def load_mapping(args):
    """Return list of (legacy, fnv, name): legacy=id2, fnv=sensorid."""
    rows = mysql(args,
                 "SELECT id2, sensorid, sensorname FROM sensorconfig "
                 "WHERE id2 IS NOT NULL AND id2 <> '' AND sensorid <> '%s'" % SENTINEL)
    return [(r[0], r[1], r[2] if len(r) > 2 else "") for r in rows]


def count(args, table, where):
    rows = mysql(args, "SELECT COUNT(*) FROM %s WHERE %s" % (table, where))
    return int(rows[0][0]) if rows else 0


def main():
    ap = argparse.ArgumentParser(description="Re-key measurement tables legacy->FNV (collision-safe).")
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--user", default="dbuser")
    ap.add_argument("--pwd", default=os.environ.get("DB_PASSWORD", ""))
    ap.add_argument("--db", default="mydb")
    ap.add_argument("--only-tables", default="",
                    help="Comma-separated monthly tables to restrict to (e.g. "
                         "table202605,table202606). Default: all table20%%.")
    ap.add_argument("--apply", action="store_true", help="Execute. Without it, dry run.")
    args = ap.parse_args()

    tables = all_monthly_tables(args)
    if args.only_tables:
        want = set(t.strip() for t in args.only_tables.split(",") if t.strip())
        missing = want - set(tables)
        if missing:
            sys.exit("ABORT: requested tables not found: %s" % ", ".join(sorted(missing)))
        tables = [t for t in tables if t in want]

    mapping = load_mapping(args)

    # --- collision-safe filtering -----------------------------------------
    # Drop mappings whose FNV target is also used as a legacy source (inverse/
    # chain set: the doubled unnamed placeholders). These are ambiguous.
    legacy_sources = {m[0] for m in mapping}
    filtered = []
    dropped = []
    for legacy, fnv, name in mapping:
        if legacy == fnv:
            continue
        if fnv in legacy_sources:          # target is also a source -> ambiguous
            dropped.append((legacy, fnv, name))
            continue
        filtered.append((legacy, fnv, name))
    # residual chain guard
    srcs = {m[0] for m in filtered}
    tgts = {m[1] for m in filtered}
    chain = sorted(srcs & tgts)
    if chain:
        print("ABORT: residual chain after filtering (target also source):")
        for c in chain:
            print("  %s" % c)
        sys.exit(2)
    # target uniqueness
    tlist = [m[1] for m in filtered]
    dup = sorted({x for x in tlist if tlist.count(x) > 1})
    if dup:
        print("ABORT: duplicate FNV targets: %s" % ", ".join(dup))
        sys.exit(2)

    print("=== re-key plan ===")
    print("db              : %s" % args.db)
    print("tables in scope : %s" % ", ".join(tables))
    print("mapping total   : %d" % len(mapping))
    print("rewriting (unambiguous) : %d" % len(filtered))
    print("dropped (ambiguous/inverse, LEFT UNTOUCHED) : %d" % len(dropped))
    for legacy, fnv, name in dropped:
        print("   SKIP  legacy=%-20s -> fnv=%-20s (%s)" % (legacy, fnv, name))
    # ----------------------------------------------------------------------

    total_before = {}
    print("\n-- per-table rewrites --")
    for t in tables:
        total_before[t] = count(args, t, "1=1")
        n_rows = 0
        for legacy, fnv, name in filtered:
            c = count(args, t, "sensorid='%s'" % sql_escape(legacy))
            if c:
                n_rows += c
                print("UPDATE %s SET sensorid='%s' WHERE sensorid='%s';  -- %s (%d rows)"
                      % (t, fnv, legacy, name, c))
        print("  %-14s total=%d rewriteable=%d" % (t, total_before[t], n_rows))

    if not args.apply:
        print("\n(dry run — pass --apply to execute)")
        return

    stmts = ["START TRANSACTION;"]
    for t in tables:
        for legacy, fnv, name in filtered:
            stmts.append("UPDATE %s SET sensorid='%s' WHERE sensorid='%s';"
                         % (t, sql_escape(fnv), sql_escape(legacy)))
    stmts.append("COMMIT;")
    print("\nApplying %d statements in one transaction..." % len(stmts))
    mysql(args, "\n".join(stmts), want_rows=False)

    print("=== verification (row counts must be unchanged) ===")
    ok = True
    for t in tables:
        after = count(args, t, "1=1")
        flag = "OK" if after == total_before[t] else "!! CHANGED"
        if after != total_before[t]:
            ok = False
        print("  %-14s rows=%d (before %d) %s" % (t, after, total_before[t], flag))
    print("done." if ok else "WARNING: row counts changed — investigate!")


if __name__ == "__main__":
    main()
