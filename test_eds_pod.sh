#!/bin/bash
POD=$(kubectl get pods -l app=eds -o jsonpath='{.items[0].metadata.name}')
if [[ -n "$1" ]]; then
  NODE_IP=$1
else
  NODE_IP=$(hostname -I | awk '{print $1}')
fi
echo "Running tests against: http://$NODE_IP:30164  (pod: $POD)"
echo ""
PASS=0; FAIL=0

GREEN='\033[0;32m'; RED='\033[0;31m'; NC='\033[0m'

check() {
  local desc=$1; shift
  if "$@" &>/dev/null; then
    echo -e "${GREEN}[PASS]${NC} $desc"; ((PASS++))
  else
    echo -e "${RED}[FAIL]${NC} $desc"; ((FAIL++))
  fi
}

kexec() { kubectl exec "$POD" -- bash -c "$1"; }

# Task 1: Process health
check "eds running"        kexec "pgrep -x eds"
check "hueTemps.py running" kexec "pgrep -f hueTemps.py"
check "cron running"       kexec "pgrep cron"
check "apache2 running"    kexec "pgrep apache2"
check "mysqld running"     kexec "pgrep mysqld"

# Task 2: MySQL
check "MySQL reachable"      kexec "mysql -u dbuser -pkmjmkm54C# -e 'SELECT 1'"
check "sensor tables present" kexec "mysql -u dbuser -pkmjmkm54C# mydb -e 'SHOW TABLES' | grep -q ."

# Task 3: XML freshness (hueTemps writes every 10 min, allow 15)
check "details.xml updated <15min" kexec "find /mnt/ramdisk -name details.xml -mmin -15 | grep -q ."

# Task 4: HTTP endpoint
check "Apache HTTP 200"   bash -c "[[ \$(curl -s -o /dev/null -w '%{http_code}' http://$NODE_IP:30164/) == 200 ]]"
check "sensorcfg.php reachable" bash -c "[[ \$(curl -s -o /dev/null -w '%{http_code}' http://$NODE_IP:30164/sensorcfg.php) == 200 ]]"

# Task 5: Cron log freshness
check "cron ran <10min" kexec "find /var/log/cron.log -mmin -10 | grep -q ."

# Task 6: PHP content & error checks
check "sensorcfg.php renders sensor rows" bash -c "[[ \$(curl -s http://$NODE_IP:30164/sensorcfg.php | grep -c '<tr>') -ge 10 ]]"
check "create_ips.php reachable"          bash -c "[[ \$(curl -s -o /dev/null -w '%{http_code}' http://$NODE_IP:30164/create_ips.php) == 200 ]]"
check "sensorcfg.php no PHP errors"       bash -c "! curl -s http://$NODE_IP:30164/sensorcfg.php  | grep -qE 'Fatal error|Warning:|Parse error'"
check "create_ips.php no PHP errors"      bash -c "! curl -s http://$NODE_IP:30164/create_ips.php | grep -qE 'Fatal error|Warning:|Parse error'"

# Task 7: PHP cron PNG existence
PICS=/var/www/html/picture
for f in homeAutoGraphMob4 homeAuto_report homeAuto_report_office homeAuto_pedo_Bar homeAuto_graph homeAuto_winddir wind CH1humidity; do
  check "$f.png exists" kexec "[ -f $PICS/$f.png ]"
done

# Task 8: PHP cron PNG freshness (<10 min, cron runs every 5 min)
for f in homeAutoGraphMob4 homeAuto_report homeAuto_report_office homeAuto_pedo_Bar homeAuto_graph homeAuto_winddir wind CH1humidity; do
  check "$f.png updated <10min" kexec "find $PICS/$f.png -mmin -10 | grep -q ."
done

# Task 9: Apache serves a PHP-generated image
check "Apache serves picture/homeAuto_graph.png" bash -c "[[ \$(curl -s -o /dev/null -w '%{http_code}' http://$NODE_IP:30164/picture/homeAuto_graph.png) == 200 ]]"

# Task 10: HTTP performance (<500ms)
perf_check() {
  local desc=$1 url=$2
  local ms=$(curl -s -o /dev/null -w "%{time_total}" "$url" | awk '{printf "%d", $1*1000}')
  if [[ $ms -lt 500 ]]; then
    echo -e "${GREEN}[PASS]${NC} $desc (${ms}ms)"; ((PASS++))
  else
    echo -e "${RED}[FAIL]${NC} $desc (${ms}ms, threshold 500ms)"; ((FAIL++))
  fi
}
perf_check "HTTP / response time"              "http://$NODE_IP:30164/"
perf_check "HTTP sensorcfg.php response time"  "http://$NODE_IP:30164/sensorcfg.php"
perf_check "HTTP create_ips.php response time" "http://$NODE_IP:30164/create_ips.php"

echo ""
echo "$PASS/$((PASS+FAIL)) tests passed"
[[ $FAIL -eq 0 ]]
