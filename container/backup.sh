#!/bin/bash

# Storage location for dumps/archives. Overridable for tests; defaults to the
# in-pod PVC mount so production behaviour is unchanged.
STORAGE_DIR="${STORAGE_DIR:-/usr/storage}"

# Check that the DB has meaningful data before backing up.
# Sum rows across ALL monthly measurement tables (table20*) rather than only the
# current month: at the start of a new month the current table is near-empty,
# which previously caused a legitimate backup to be skipped and left the newest
# dump stale. Summing all months reflects whether the DB actually holds data.
ROWS=$(mysql -N -e "
  SELECT COALESCE(SUM(table_rows),0)
  FROM information_schema.tables
  WHERE table_schema='mydb' AND table_name LIKE 'table20%'" 2>/dev/null)
if [[ -z "$ROWS" || "$ROWS" -lt 1000 ]]; then
  echo "Skipping backup — DB looks incomplete ($ROWS rows across monthly tables)"
  exit 0
fi

# Persist the sensorconfig seed ONLY if the live table looks healthy. Otherwise
# a transiently-polluted table (many placeholder 'name' rows, e.g. right after
# the collector auto-created rows for new sensorids) would be dumped over the
# PVC seed and then faithfully reloaded on the next restart — which is exactly
# how real names got demoted. Refuse to persist a degenerate table so the
# previous good seed on the PVC is kept instead.
NAMED=$(mysql -N -e \
  "SELECT COUNT(*) FROM mydb.sensorconfig WHERE sensorname <> 'name'" 2>/dev/null)
PLACEHOLDERS=$(mysql -N -e \
  "SELECT COUNT(*) FROM mydb.sensorconfig WHERE sensorname = 'name'" 2>/dev/null)
if [[ -z "$NAMED" || "$NAMED" -lt 1 || ( -n "$PLACEHOLDERS" && "$PLACEHOLDERS" -ge "$NAMED" ) ]]; then
  echo "Skipping sensorconfig.sql dump — table looks degenerate (named=$NAMED placeholders=$PLACEHOLDERS); keeping existing PVC seed"
else
  mysqldump --no-create-info mydb sensorconfig > "$STORAGE_DIR"/sensorconfig.sql
fi

N_O_FILES=`ls "$STORAGE_DIR"/*.tar | wc -w`
ARR=($(ls -tr "$STORAGE_DIR"/*.tar))
i=0

echo $N_O_FILES

if [[ $N_O_FILES -ge 10 ]]
then
  echo "Removing ${ARR[0]}"
  rm -f ${ARR[0]}
  ((N_O_FILES--))
  ((i++))
fi

if [[ $N_O_FILES -ge 1 ]]
then
  ((N_O_FILES++))
  while [ $N_O_FILES -ge 2 ]
  do
    mv ${ARR[$i]} `echo ${ARR[$i]} | sed -r "s/[0-9]+/$N_O_FILES/g"`
    ((i++))
    ((N_O_FILES--))
  done
fi

mysqldump mydb > "$STORAGE_DIR"/test1.sql
tar -czf "$STORAGE_DIR"/test1.tar "$STORAGE_DIR"/test1.sql
rm -f "$STORAGE_DIR"/test1.sql
echo "Backup complete ($ROWS rows across monthly tables)"
