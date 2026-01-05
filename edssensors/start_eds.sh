#!/bin/bash
#./eds 192.168.50.40 192.168.50.79 192.168.50.230 10.96.120.81 10.105.80.211 127.0.0.1
kill -9 $(pgrep -x eds)
../../../homedata/edssensors/eds "$(< /usr/storage/ips/ips.txt)"
