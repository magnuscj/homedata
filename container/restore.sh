#!/bin/bash

# Restore the database from the newest VALID backup in /usr/storage.
#
# backup.sh rotates dumps so that test1.tar is always the newest and higher
# numbers are progressively older (test10.tar oldest). We therefore try
# candidates in newest-first order by numeric index and use the first one that
# both extracts cleanly and contains test1.sql. This is more reliable than the
# previous "largest file" heuristic: a stale-but-large dump can no longer win
# over a fresh one, and a corrupt newest backup gracefully falls back to the
# next-newest instead of aborting startup.

# Build the candidate list ordered newest -> oldest (numeric sort on the index).
mapfile -t CANDIDATES < <(
  ls /usr/storage/test*.tar 2>/dev/null \
    | sed -E 's#.*/test([0-9]+)\.tar#\1 &#' \
    | sort -n \
    | awk '{print $2}'
)

if [[ ${#CANDIDATES[@]} -eq 0 ]]; then
  echo "No backup found in /usr/storage/, starting fresh"
  echo "CREATE DATABASE IF NOT EXISTS mydb;" | mysql
  exit 0
fi

restore_from() {
  # $1 = path to tar. Returns 0 on successful import, non-zero otherwise.
  local backup="$1"
  local workdir
  workdir=$(mktemp -d)

  # Extract into an isolated dir so a partial/corrupt tar can't leave a stale
  # test1.sql lying around for the next candidate.
  if ! tar -xf "$backup" -C "$workdir" --strip-components=2 2>/dev/null; then
    echo "WARN: failed to extract $backup, trying next" >&2
    rm -rf "$workdir"
    return 1
  fi
  if [[ ! -s "$workdir/test1.sql" ]]; then
    echo "WARN: test1.sql missing/empty in $backup, trying next" >&2
    rm -rf "$workdir"
    return 1
  fi

  echo "Restoring from: $backup ($(du -h "$backup" | cut -f1))"
  echo "DROP DATABASE IF EXISTS mydb; CREATE DATABASE mydb;" | mysql
  if [[ $? -ne 0 ]]; then
    echo "ERROR: Failed to create database" >&2
    rm -rf "$workdir"
    return 1
  fi
  if ! mysql mydb < "$workdir/test1.sql"; then
    echo "WARN: import failed from $backup, trying next" >&2
    rm -rf "$workdir"
    return 1
  fi

  rm -rf "$workdir"
  echo "Restore complete"
  return 0
}

for backup in "${CANDIDATES[@]}"; do
  if restore_from "$backup"; then
    exit 0
  fi
done

echo "ERROR: all backups in /usr/storage/ failed to restore" >&2
exit 1
