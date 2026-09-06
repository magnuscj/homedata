#!/usr/bin/env python3
"""
add_id2.py — Migration bridge: add an `id2` column to an existing `sensorconfig`
table and populate it with the deterministic FNV-1a 64-bit hash derived from each
sensor's details.xml, matched to the existing (legacy std::hash) `sensorid`.

WHY
    The legacy sensor id is std::to_string(std::hash<std::string>(ROMId+metricType+type)),
    which is NOT stable across builds/architectures. `id2` is a deterministic
    replacement (FNV-1a) that can be recomputed anywhere. Keeping both columns
    side by side lets you map an old deployment to the new scheme and migrate later.

WHAT IT DOES
    1. Reads DB credentials + sensorTypes from edsServerHandlerConf.txt (same as eds).
    2. Reads IPs from the ips file (default /usr/storage/ips/ips.txt).
    3. For every IP, fetches http://<ip>/details.xml and, for each sensor, rebuilds
       the exact eds input string  ROMId + metricType + type  and computes
       id2 = FNV-1a-64(input) as a decimal string.
    4. Adds the `id2` column to sensorconfig if it does not already exist.
    5. Matches each computed id2 to an existing row and sets id2 there.

MATCHING (old sensorid -> row)
    The old sensorid cannot be recomputed from a formula. Two modes:
      --match oldhash  (default): reproduce the legacy std::hash by compiling a
          tiny C++ helper with the LOCAL toolchain (must be the same libstdc++ as
          the deployed eds binary). UPDATE ... WHERE sensorid = <old_hash>.
      --match id2     : the table's sensorid ALREADY equals the new FNV-1a id
          (i.e. a system already migrated to the deterministic hash). Match on that.

SAFETY
    Dry-run by default: prints the planned UPDATEs and a summary but changes nothing.
    Pass --apply to execute (adds the column + runs UPDATEs in a transaction).

USAGE
    python3 add_id2.py                 # dry run against defaults
    python3 add_id2.py --apply         # perform the migration
    python3 add_id2.py --conf ./edsServerHandlerConf.txt --ips ./ips.txt --apply
"""
import argparse
import os
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET

NON_SENSOR_KEYS = {
    "dbip", "dbuser", "dbpwd",
    "smtp_user", "smtp_pwd", "smtp_from", "smtp_to",
}

OLDHASH_CC = r'''
#include <iostream>
#include <string>
#include <functional>
int main(){
    std::string line;
    while(std::getline(std::cin,line)){
        if(!line.empty() && line.back()=='\r') line.pop_back();
        std::cout<<std::to_string(std::hash<std::string>{}(line))<<"\t"<<line<<"\n";
    }
    return 0;
}
'''


# ---------- hashing ----------
def fnv1a_64(s: str) -> int:
    h = 0xcbf29ce484222325
    for b in s.encode("utf-8"):
        h ^= b
        h = (h * 0x100000001b3) & 0xFFFFFFFFFFFFFFFF
    return h


# ---------- config parsing (mirrors edsServerHandler ctor) ----------
def parse_conf(path):
    """Return (db_cfg, sensor_types). sensor_types preserves order and includes
    any XX-prefixed keys (they simply never match a live node name)."""
    db_cfg = {}
    sensor_types = []
    with open(path) as f:
        for line in f:
            toks = line.split()          # mimics `iss >> item >> value`
            if len(toks) < 2:
                continue
            item, value = toks[0], toks[1]
            if item == "dbip":
                db_cfg["host"] = value
            elif item == "dbuser":
                db_cfg["user"] = value
            elif item == "dbpwd":
                db_cfg["pwd"] = value
            elif item in NON_SENSOR_KEYS:
                continue
            else:
                sensor_types.append((item, value))
    return db_cfg, sensor_types


def localname(tag):
    return tag.split("}", 1)[1] if "}" in tag else tag


def fetch_xml(ip, timeout=5):
    out = subprocess.run(
        ["curl", "-s", "--max-time", str(timeout), "http://%s/details.xml" % ip],
        capture_output=True,
    )
    return out.stdout.decode("utf-8", "replace")


def child_text(node, target_local):
    for c in list(node):
        if localname(c.tag) == target_local:
            return c.text if c.text is not None else ""
    return None


def compute_sensors(ips, sensor_types):
    """Return list of dicts: {id2, type, metric, romid, ip, input}."""
    rows = []
    for ip in ips:
        xml = fetch_xml(ip)
        if not xml.strip():
            sys.stderr.write("WARN: empty response from %s\n" % ip)
            continue
        try:
            root = ET.fromstring(xml)
        except ET.ParseError as e:
            sys.stderr.write("WARN: XML parse error from %s: %s\n" % (ip, e))
            continue
        for node in list(root):
            name = localname(node.tag)
            for item, metric in sensor_types:
                if item != name:
                    continue
                romid = child_text(node, "ROMId")
                if romid is None:
                    sys.stderr.write("WARN: %s node %s has no ROMId; skipping\n"
                                     % (ip, name))
                    continue
                inp = romid + metric + name
                rows.append({
                    "id2": str(fnv1a_64(inp)),
                    "type": name, "metric": metric, "romid": romid,
                    "ip": ip, "input": inp,
                })
    return rows


# ---------- old std::hash bridge ----------
def reproduce_old_hashes(inputs):
    """Compile the C++ helper with the local toolchain and map input->old_id.
    Returns {} if no C++ compiler is available."""
    cxx = None
    for cand in ("g++", "c++", "clang++"):
        if subprocess.run(["which", cand], capture_output=True).returncode == 0:
            cxx = cand
            break
    if cxx is None:
        sys.stderr.write("ERROR: no C++ compiler (g++) found; cannot reproduce "
                         "legacy std::hash. Use --match id2 if sensorid already "
                         "holds the deterministic hash.\n")
        return {}
    with tempfile.TemporaryDirectory() as d:
        src = os.path.join(d, "oldhash.cc")
        binf = os.path.join(d, "oldhash")
        with open(src, "w") as f:
            f.write(OLDHASH_CC)
        r = subprocess.run([cxx, "-O2", "-o", binf, src], capture_output=True)
        if r.returncode != 0:
            sys.stderr.write("ERROR: compiling oldhash helper failed:\n%s\n"
                             % r.stderr.decode("utf-8", "replace"))
            return {}
        proc = subprocess.run([binf], input="\n".join(inputs).encode("utf-8"),
                              capture_output=True)
        mapping = {}
        for line in proc.stdout.decode("utf-8", "replace").splitlines():
            parts = line.split("\t", 1)
            if len(parts) == 2:
                mapping[parts[1]] = parts[0]   # input -> old_id
        return mapping


# ---------- mysql helpers ----------
def mysql(db_cfg, sql, db="mydb", capture=True):
    cmd = ["mysql", "-h", db_cfg.get("host", "127.0.0.1"),
           "-u", db_cfg.get("user", "dbuser"),
           "-p" + db_cfg.get("pwd", ""), "-N", "-B", "-e", sql, db]
    return subprocess.run(cmd, capture_output=capture)


def column_exists(db_cfg, db, table, col):
    r = mysql(db_cfg,
              "SELECT COUNT(*) FROM information_schema.COLUMNS "
              "WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='%s' AND COLUMN_NAME='%s'"
              % (db, table, col))
    return r.stdout.decode().strip() not in ("", "0")


# ---------- main ----------
def main():
    ap = argparse.ArgumentParser(description="Add id2 column and populate FNV-1a hashes from details.xml.")
    ap.add_argument("--conf", default="edsServerHandlerConf.txt")
    ap.add_argument("--ips", default="/usr/storage/ips/ips.txt")
    ap.add_argument("--db", default="mydb")
    ap.add_argument("--table", default="sensorconfig")
    ap.add_argument("--match", choices=["oldhash", "id2"], default="oldhash",
                    help="How to match computed id2 to existing rows "
                         "(oldhash = reproduce legacy std::hash; "
                         "id2 = sensorid already holds the deterministic hash).")
    ap.add_argument("--apply", action="store_true",
                    help="Execute changes. Without this flag it is a dry run.")
    args = ap.parse_args()

    db_cfg, sensor_types = parse_conf(args.conf)
    with open(args.ips) as f:
        ips = [l.strip() for l in f if l.strip()]

    rows = compute_sensors(ips, sensor_types)
    # dedupe identical inputs (same sensor listed twice in a device's XML)
    by_input = {}
    for r in rows:
        by_input[r["input"]] = r
    uniq = list(by_input.values())

    # collision check: distinct inputs must map to distinct id2
    id2_seen = {}
    collisions = []
    for r in uniq:
        prev = id2_seen.get(r["id2"])
        if prev and prev != r["input"]:
            collisions.append((r["id2"], prev, r["input"]))
        id2_seen[r["id2"]] = r["input"]

    # build input -> match-key (the value to match against sensorid)
    if args.match == "oldhash":
        old = reproduce_old_hashes([r["input"] for r in uniq])
        if not old:
            sys.exit(1)
        for r in uniq:
            r["match_key"] = old.get(r["input"])
    else:  # id2: sensorid already equals the deterministic hash
        for r in uniq:
            r["match_key"] = r["id2"]

    # plan UPDATEs
    updates = [(r["match_key"], r["id2"], r) for r in uniq if r["match_key"]]

    print("=== plan ===")
    print("IPs                : %d" % len(ips))
    print("sensor instances   : %d" % len(rows))
    print("distinct sensors   : %d" % len(uniq))
    print("distinct id2       : %d" % len(set(r["id2"] for r in uniq)))
    print("collisions         : %d" % len(collisions))
    for c in collisions:
        print("   COLLISION id2=%s : %r vs %r" % c)
    print("match mode         : %s" % args.match)
    print("rows to update     : %d" % len(updates))
    print()
    for match_key, id2, r in updates:
        print("UPDATE %s SET id2=%s WHERE sensorid=%s;   -- %s %s %s"
              % (args.table, id2, match_key, r["type"], r["metric"], r["romid"]))

    if not args.apply:
        print("\n(dry run — pass --apply to execute)")
        return

    if collisions:
        sys.exit("ABORT: id2 collisions detected; not applying.")

    # add column if missing
    if not column_exists(db_cfg, args.db, args.table, "id2"):
        r = mysql(db_cfg, "ALTER TABLE %s ADD COLUMN id2 TEXT NULL AFTER sensorid"
                  % args.table, db=args.db)
        if r.returncode != 0:
            sys.exit("ERROR adding id2 column:\n" + r.stderr.decode())
        print("id2 column added.")
    else:
        print("id2 column already present.")

    # apply updates in one transaction
    stmts = ["START TRANSACTION;"]
    for match_key, id2, r in updates:
        stmts.append("UPDATE %s SET id2=%s WHERE sensorid=%s;"
                     % (args.table, id2, match_key))
    stmts.append("COMMIT;")
    r = mysql(db_cfg, "\n".join(stmts), db=args.db)
    if r.returncode != 0:
        sys.exit("ERROR applying updates:\n" + r.stderr.decode())

    # report
    r = mysql(db_cfg, "SELECT COUNT(*), SUM(id2 IS NOT NULL) FROM %s" % args.table,
              db=args.db)
    total_with = r.stdout.decode().strip()
    print("done. (total, with_id2) =", total_with)


if __name__ == "__main__":
    main()
