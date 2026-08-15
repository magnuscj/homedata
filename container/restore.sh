#!/bin/bash

# Pick the best backup: largest tar file (most data = most likely pre-crash)
BACKUP=$(ls -S /usr/storage/test*.tar 2>/dev/null | head -1)

if [[ -z "$BACKUP" ]]; then
  echo "No backup found in /usr/storage/, starting fresh"
  echo "CREATE DATABASE IF NOT EXISTS mydb;" | mysql
  exit 0
fi

echo "Restoring from: $BACKUP ($(du -h "$BACKUP" | cut -f1))"

tar -xf "$BACKUP" --strip-components=2
if [[ $? -ne 0 ]]; then
  echo "ERROR: Failed to extract $BACKUP" >&2
  exit 1
fi

if [[ ! -f test1.sql ]]; then
  echo "ERROR: test1.sql not found after extraction" >&2
  exit 1
fi

echo "DROP DATABASE IF EXISTS mydb; CREATE DATABASE mydb;" | mysql
if [[ $? -ne 0 ]]; then
  echo "ERROR: Failed to create database" >&2
  exit 1
fi

mysql mydb < test1.sql
if [[ $? -ne 0 ]]; then
  echo "ERROR: Failed to import database dump" >&2
  exit 1
fi

rm -f test1.sql
echo "Restore complete"
