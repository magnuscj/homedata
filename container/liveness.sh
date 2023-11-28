#!/bin/bash
pgrep eds
if [ $? -eq 0 ]; then
  echo 0
else
  echo "EDS not running!" 1>&2
  exit 1
fi

pgrep python3
if [ $? -eq 0 ]; then
  echo 0
else
  echo "Huetemp not running!" 1>&2
  exit 1
fi