#!/bin/bash

pgrep python3 >/dev/null
if [ $? -eq 0 ]; then
  echo 0
else
  echo "rain is not running!" 1>&2
  exit 1
fi

