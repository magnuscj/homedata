---
name: homedata-infra-inventory
description: Infrastructure inventory for the homedata sensor stack. Use when deploying, rolling back, running kubectl/docker, SSHing to hosts, or reasoning about prod (holken2) vs sandbox (Datorkontor) topology, NodePort maps, MySQL schema, or eds/sensorconfig data-model conventions.
---

# Homedata Infrastructure Inventory (captured 2026-09-19)

Two Kubernetes environments run the same "homedata" sensor stack (apps: eds, hue, humi, rain, wind). Source repo: git@github.com:magnuscj/homedata.git.

## PRODUCTION — host "holken2"
- Access: `ssh magnus@192.168.50.171` (SSH key auth, key ~/.ssh/id_ed25519; working as of 2026-09-19).
- OS: Ubuntu 22.04.5 LTS, kernel 6.8.0-111-generic. Host IPs: 192.168.50.171 (LAN), 192.168.49.1 (minikube bridge), 172.17.0.1 (docker).
- Kubernetes: minikube v1.32.0, docker driver (docker://24.0.7). kube context = "minikube". Single node "minikube", server v1.28.3, node internal IP 192.168.49.2, cluster age ~2y312d. (kubectl client v1.36.3 -> version-skew warning vs server 1.28.)
- App pods (ns default), all 1/1 Running:
  - eds-deployment (pod name changes per rollout)  image magnuscj/eds:eds-t63 (as of 2026-09-19; prior eds-t62)
  - hue-deployment   magnuscj/hue:prod_4
  - humi-deployment  magnuscj/humi:prod_9
  - rain-deployment  magnuscj/rain:prod_10
  - wind-deployment  magnuscj/wind:prod_2
  - plus kube-system (coredns, etcd, apiserver, controller-manager, scheduler, kube-proxy, storage-provisioner) and kubernetes-dashboard ns.
- Services (NodePort, all target port 80): eds ->30164, wind ->30165, rain ->30166, hue ->30167. No ingress. ClusterIPs differ from sandbox.
- Storage: PVC eds-pv-claim -> PV eds-pv-volume, 1Gi, RWO, storageclass "manual", RECLAIM Retain. Mounted in eds pod as volume "eds-pv-storage" at /usr/storage.
- eds pod (single container "eds", containerPort 80 named eds-cont-port) runs internally: mysqld 8.0.46, apache2 (root+www-data), cron, the collector `./eds 192.168.50.230 <service clusterIPs...>`, `python3 eds_web.py`, `python3 hueTemps.py` (from /homedata/scripts).
- MySQL DBs: mydb (+ information_schema, mysql, performance_schema, sys).
- mydb tables (prod): as of 2026-09-27 the historical cleanup left ONLY `sensorconfig` (54 rows) and `table202609`. The older monthly tables (table202605..202608), the standalone `electricityprice` table, and the migration/backup tables (sensor_id_migration_log, sensorconfig_dupe_repair_backup, sensorconfig_null_id2_removed_backup) were dropped (intentional cleanup). NOTE: prod and sandbox share the SAME canonical FNV sensorids (e.g. Ute=16261054761367928446, El=10631191365266147712, Heater=12837279452181235050); the "sensorids are different" only means the sensorconfig row set/ids differ, not the FNV hashes. (Pre-cleanup this listed: sensorconfig 6-col NO id2, table202605..202609, electricityprice, + the migration artifacts.)
- Repo on prod: `~/repo/homedata` (= /home/magnus/repo/homedata), git remote git@github.com:magnuscj/homedata.git, branch WIP. (NOTE: there is NO ~/homedata on prod; the checkout lives under ~/repo/.) As of 2026-09-19 it is at commit 84f9be3.
- Prod deploy workflow: run ON holken2 from ~/repo/homedata. Prod kube context = "minikube".
  - `./build.sh <tag>` = the production deploy path: docker build -t magnuscj/eds:<tag> . ; docker push (Docker Hub, logged in as magnuscj via ~/.docker/config.json — plaintext base64 cred, consider rotating); then `kubectl set image deployments/eds-deployment eds=magnuscj/eds:<tag>` (rolling update). Image tags follow eds-tNN (t57..t63 present on host).
  - `./deploy_local.sh` also exists (timestamp local-* tag; on minikube context it does `minikube image load` + rollout restart).
  - Rollback: `kubectl set image deployments/eds-deployment eds=magnuscj/eds:<previous-tag>`.
- IMPORTANT: prod eds is deployed/managed independently from the sandbox (its own eds-tNN tags built+pushed from ~/repo/homedata on holken2). Current prod image after 2026-09-19 redeploy: magnuscj/eds:eds-t63 (was eds-t62). The sandbox's local-* images are NOT used on prod.

## SANDBOX — host "Datorkontor" (this computer)
- Local WSL2 Ubuntu 24.04.3 LTS, kernel 6.6.87.2-microsoft-standard-WSL2. Host IP 172.22.240.21. Working dir /home/magnus/homedata.
- Kubernetes: Docker Desktop Kubernetes (NOT minikube; minikube not installed). kube context = "docker-desktop". Single node "desktop-control-plane", server v1.36.1, containerd://2.3.1, internal IP 172.20.0.3, node age ~20d. Docker engine v29.7.2.
- App pods (ns default), all 1/1 Running:
  - eds-deployment-6df96c569d-29wk8  image magnuscj/eds:local-20260913073604  (locally built id2-removal image)
  - hue-deployment   magnuscj/hue:prod_4
  - humi-deployment  magnuscj/humi:prod_6
  - rain-deployment  magnuscj/rain:prod_1
  - wind-deployment  magnuscj/wind:prod_2
  - plus kube-system (2x coredns, etcd, kindnet, apiserver, controller-manager, kube-proxy, scheduler) and local-path-storage (local-path-provisioner). Uses kindnet -> kind-style cluster under Docker Desktop.
- Services (NodePort, target 80): eds ->30164, wind ->30166, humi ->30165, hue ->30167, rain ->30168. NOTE port map differs from prod (sandbox: humi=30165, wind=30166, rain=30168; prod has no humi service and wind=30165, rain=30166).
- Storage: PVC eds-pv-claim -> PV eds-pv-volume 1Gi RWO "manual" Retain (same names as prod, separate cluster).
- eds pod internals: mysqld 8.0.46, apache2, collector `./eds 192.168.50.230 192.168.50.79 <service clusterIPs...>`, eds_web.py, hueTemps.py.
- mydb tables (sandbox): sensor_id_migration_log, sensor_id_swap_map, sensorconfig (6-col no id2), table202608, table202609. (Note sandbox has sensor_id_swap_map which prod does not; prod has dupe_repair/null_id2 backups which sandbox does not.)

## Repo / deploy tooling (in /home/magnus/homedata)
- git remote origin git@github.com:magnuscj/homedata.git; current branch WIP. Latest commit 54c989a "sensorconfig: remove legacy id2 column; use FNV sensorid only".
- Deploy scripts: deploy_local.sh (builds magnuscj/eds:local-<timestamp>, loads to minikube if that context, sed REPLACE->tag in eds.yaml -> eds_deploy.yaml, kubectl apply + rollout restart), build.sh, port_forward.sh, test_eds_pod.sh.
- Manifests: eds.yaml (image placeholder magnuscj/eds:REPLACE, 2 occurrences), eds-rbac.yaml, eds-vol.yaml, eds_deploy.yaml (generated).

## Data-model notes (eds / sensorconfig)
- sensorid = canonical FNV-1a 64-bit hash (portable). Legacy std::hash id (formerly column id2) was removed 2026-09 from schema and all code.
- Monthly measurement tables tableYYYYMM keyed on sensorid. On prod, table202605 fully FNV-keyed; table202606-202608 have ~15 unjoinable ids that are unnamed placeholders + a stray "electricityprice" key (NOT recoverable to names; no mapping exists). Migration backup tables hold the only legacy<->FNV mapping record — keep them.

## Backups taken
- Sandbox pre-id2-removal: /home/magnus/homedata/backups/pre_id2_removal_20260913_071315.sql (sensorconfig+table202608+table202609).
- Prod June table backup: /home/magnus/homedata/backups/prod_table202606_20260919_064653.sql (83MB, full table202606).

## Session update (2026-09-27): elprice pod + eds sensor-source mechanism

### New app: elprice pod (electricity spot price)
- Source: `container/elpricepod/` (modeled on the rainpod pattern). Build/push/deploy with the generic `container/build_pod.sh elprice [tag]` (the former `build_elprice.sh` was removed 2026-10-03; build_pod.sh supersedes it — same behaviour for image `magnuscj/elprice` / deployment `elprice-deployment`: Docker Hub preflight, substitutes REPLACE in `elprice_deploy.yaml` -> `elprice_deploy.gen.yaml`, applies the full manifest, prunes old images).
- What it does: cron runs `elprice.py` which fetches SE3 day-ahead prices from `https://www.elprisetjustnu.se/api/v1/prices/{Y}/{m}-{d}_SE3.json` (NOTE: the API now returns 15-min intervals; it rejects the default python-urllib User-Agent with HTTP 403, so a UA header is set). Like eds/getElectricityPrices.py it also pre-fetches TOMORROW after ~13:00. It writes:
  - `/mnt/ramdisk/details.xml` — ONLY the single currently-valid price (EDS `owd_ELPRICE`/`Price` node, ROMId `ELPRICE:SE3`).
  - `/mnt/ramdisk/electricityprice.csv` — the full forward-looking curve (today+tomorrow), upserted by interval_start.
  - Both are exposed via apache (symlinked into /var/www/html): `/details.xml` and `/electricityprice.csv`. `/mnt/ramdisk` is ephemeral (no PVC) so CSV history resets on pod restart.
- Service: `elprice-ext-nordenort-service` (follows the `<app>-ext-nordenort-service` convention), NodePort 30169 (sandbox). Sandbox NodePort map is now: eds 30164, humi 30165, wind 30166, hue 30167, rain 30168, elprice 30169.
- Sandbox image so far: `magnuscj/elprice:elprice-t2`. NOT deployed to prod yet.

### How eds picks up a new sensor source (reusable mechanism)
- eds is launched by `start_eds.sh` as `./eds $(< /usr/storage/ips/ips.txt)` — the poll list is the PVC file `/usr/storage/ips/ips.txt`, NOT the repo `ips.txt` (repo copy is only the seed baked into the image). To add a source live: update the PVC file AND restart the collector (`sudo /homedata/edssensors/start_eds.sh`). The repo `ips.txt` pins each service by ClusterIP.
- For each polled IP eds GETs `http://<ip>/details.xml`, and for every XML node whose element name matches a line in `edssensors/edsServerHandlerConf.txt` (`<nodeName> <metricElement>`) it reads that node's `ROMId` + `<metricElement>` and computes the canonical id: `sensorid = FNV-1a(ROMId + metricElement + nodeName)`. Unknown ids get an auto-created sensorconfig row (name 'name', type 'default') which you then rename.
- To add the price to eds: added `owd_ELPRICE Price` to `edsServerHandlerConf.txt` (requires an eds rebuild — the conf is read at runtime from the baked-in /homedata/edssensors). Price sensorid = FNV-1a("ELPRICE:SE3"+"Price"+"owd_ELPRICE") = `7367161076056225378`; its sensorconfig row was renamed to sensorname 'Pris', type 'price'.

### Visualization added
- `visualize/homeAuto_report.php`: added a 'Pris <val> Sek' tile (type `price` branch); removed the Vind block and the Fry_gr/Garage mini-list; El/Fukt/Värme/Regn/Pris evenly spread; Pris kept left of x=297 so it does not sit under status.png.
- `visualize/homeAuto_elprice_graph.php` (new): 395x219 bar graph of future 15-min prices fetched over HTTP from the elprice pod (URL via new `ELPRICEURL` key in `visualize/config.txt`). 4 color bands over the visible price range (green lowest quarter -> yellow -> orange -> red). Rendered every 5 min via `container/cron_eds`.
- `visualize/android_F_S_dynamic.html`: the p6 image now click-toggles between `homeAuto_pedo_Bar.png` and `homeAuto_elprice_graph.png`, persisted in localStorage across auto-refresh/reload.

### Gotchas / facts
- status.png is 109x104 at dashboard position (297,210) -> its opaque area covers x∈[297,406], y∈[210,314]. Report content must stay left of x=297 to avoid being hidden.
- Docker Desktop (sandbox) does NOT expose raw NodePorts on localhost the way minikube does; reach services from the WSL/host via `kubectl port-forward service/<svc> <port>:80` (see port_forward.sh for the eds pattern).
- PHP in the eds pod has `allow_url_fopen=1`, so `file_get_contents("http://...")` works for cross-pod HTTP fetches.
- Local sandbox tooling for image inspection/plots: `/tmp/plotvenv` (python venv with matplotlib + pillow) was created this session.
- Git: work is on branch `WIP` (tracks origin/WIP); do not push to main/master. Selective staging is used (many unrelated pre-existing modified/untracked files exist in the tree — id2 migration scripts, backups/, local tools — left uncommitted).

## Session update (2026-09-29): sensorconfig invariant + type-case fix + external access port-forward

### Deploys
- Prod eds is now `magnuscj/eds:eds-t66` (built from repo commit `05ade2d` on holken2 after `git pull` of origin/WIP; the earlier `eds-t65` was a build from PRE-pull code and did NOT contain the fix). Sandbox ran `local-*` builds of the same work.
- Commit `05ade2d` ("sensorconfig: protect named rows + case-insensitive sensor type") is on origin/WIP.

### sensorconfig "named row protection" invariant (now enforced)
- Rule: a `sensorconfig` row whose `sensorname` != 'name' is NEVER overwritten/demoted except by explicit user action (sensorcfg.php UI).
- `container/migrate_sensorconfig_unique.sql` (new, shipped to the image next to createSensorConfig.sh via Dockerfile): idempotent de-dup by sensorid (keep the named row, else lowest id) + `ADD UNIQUE KEY uk_sensorid (sensorid(64))`. Prod now HAS `uk_sensorid`.
- `container/createSensorConfig.sh` (rewritten): never DROPs the live table; `CREATE TABLE IF NOT EXISTS` -> run migration -> fill-only merge of PVC dump (via staging table, DROP/CREATE neutralised) -> fill-only merge of hardcoded SEED. Fill-only = `INSERT ... ON DUPLICATE KEY UPDATE` with every column guarded by `sensorconfig.sensorname='name'`, and the discriminator (`sensorname`) assigned LAST (MySQL evaluates ODKU left-to-right and later exprs see already-updated cols — assign the guard column last or the guards misfire). SEED now includes Pris (sensorid 7367161076056225378, type 'price').
- `container/backup.sh`: refuses to dump `sensorconfig.sql` to the PVC when the table looks degenerate (no named rows, or placeholders >= named) so pollution can't round-trip through the PVC seed.
- `edssensors/edsServerHandler.cc`: decodeXml uses `.find()` not map `operator[]` (no auto-insert side effect); `writeSensorConfiguration` is now `INSERT ... ON DUPLICATE KEY UPDATE sensorname=sensorname` (needs uk_sensorid).
- Root cause of the original "overwritten sensors": a polluted `/usr/storage/sensorconfig.sql` (captured by backup.sh while the table held placeholder rows) was reloaded on pod restart; the old weak guard (`any named row present?`) let it stand. Manual recovery that worked at the time: delete `/usr/storage/sensorconfig.sql` and re-run createSensorConfig.sh (then the SEED fallback fired).

### type-case rendering fault (Pris tile)
- Symptom: dashboard "Pris" tile + value not rendered on prod. Cause: prod row had `type='Price'` but reports compare case-sensitively (`$type == "price"`). Data flow was fine (eds collects `owd_ELPRICE`/Price; measurements updating).
- Immediate prod fix: `UPDATE mydb.sensorconfig SET type='price' WHERE sensorid='7367161076056225378';`
- Long-term fix (in 05ade2d): `visualize/homeFunctions.php` getSensorNames() now `strtolower(trim())`s `type` at the single read source (all reports become case-insensitive on type); `sensorcfg.php` lowercases `type` on save. This kills the whole class of bug.

### EXTERNAL public access to the dashboard (nginx reverse proxy — current, 2026-10-25)
- Public URL `http://81.234.147.6:8090/android_F_S_dynamic.html` = router WAN NAT -> holken2 LAN `192.168.50.171:8090` -> **nginx reverse proxy** -> eds NodePort `192.168.49.2:30164` -> eds pod. (Router target was changed 2026-10-25 from `:8181` to `:8090`.)
- The prod minikube NodePort 30164 is only bound on the minikube node IP `192.168.49.2`, NOT on the LAN IP, so external traffic still cannot hit 30164 directly — but nginx on the host CAN reach `192.168.49.2:30164`, so it proxies there directly. No `kubectl port-forward` is involved anymore.
- IMPLEMENTATION (repo `proxy/`, committed on branch WIP):
  - `proxy/nginx-eds.conf` — listens `:8090`, `proxy_pass http://192.168.49.2:30164`, sets `X-Forwarded-For`/`X-Real-IP`, and LOGS the real client IP. Internal/LAN source IPs (127.*, 10.*, 192.168.*, 172.16-31.*, 169.254.*) are filtered out of the access log via an nginx `map` + conditional `access_log`.
  - `proxy/eds-proxy.service` — systemd unit running a pinned `nginx:1.27-alpine` container (`--network host`), `Restart=always`, `enabled` (starts on boot). Installed at `/etc/systemd/system/eds-proxy.service`; config at `/etc/eds-proxy/nginx-eds.conf`.
  - `proxy/logrotate-eds` -> `/etc/logrotate.d/eds-proxy` — caps logs at 50M, 14 rotations, gzip, `copytruncate`.
  - `proxy/install.sh` — idempotent installer (config + systemd + logrotate). Run with `sudo`.
- LOGS: real external client IPs at `/home/magnus/eds-logs/eds_access.log` (host; bind-mounted into the container at `/var/log/nginx`). Internal IPs are excluded by design.
- WHY THIS REPLACED THE PORT-FORWARD: the old `kubectl port-forward` (below) logged every visitor as 127.0.0.1 (L4 loopback tunnel, no client IP, no XFF) AND was fragile (half-dead/zombie forwarder after pod rollouts). nginx fixes both: it recovers the real client IP and self-heals via systemd `Restart=always`. This implements the former "SUGGESTED HARDENING".
- BOOT: `/usr/local/bin/start-minikube.sh` was edited 2026-10-25 to REMOVE the old `while true ... port-forward 8181:80` loop; it now only runs `minikube start`. (Backup: `start-minikube.sh.bak-*` beside it.) External access is served by the `eds-proxy` systemd service, independent of that script.
- MANAGE / DIAGNOSE:
  - `systemctl status eds-proxy` ; `journalctl -u eds-proxy -f` ; `systemctl restart eds-proxy` (after editing `/etc/eds-proxy/nginx-eds.conf`).
  - `curl http://192.168.50.171:8090/android_F_S_dynamic.html` -> expect 200.
  - `curl http://192.168.49.2:30164/...` on host -> proves service/NodePort OK (nginx's upstream).
  - `kubectl exec` curl INSIDE the pod -> proves app OK.
  - `tail -f /home/magnus/eds-logs/eds_access.log` -> real visitor IPs.
- GDPR: client IPs are personal data; retention capped at 14 rotations by logrotate — adjust in `proxy/logrotate-eds` to policy and disclose logging for EU visitors.

#### SUPERSEDED (historical, pre-2026-10-25): raw kubectl port-forward on :8181
- Old path: router WAN :8090 -> holken2 `192.168.50.171:8181` via `kubectl port-forward --address 192.168.50.171 service/eds-ext-nordenort-service 8181:80`, kept alive by a bare `while true` loop in `/usr/local/bin/start-minikube.sh`.
- FAILURE MODE (seen 2026-09-29 after eds rollouts t65->t66): a pod rollout deleted the tunneled pod; `kubectl port-forward` went HALF-DEAD — kept the `:8181` LISTEN socket open but the tunnel to :80 timed out (`curl` -> HTTP 000, log spam `error creating error stream for port 8181 -> 80: Timeout occurred`). The process did NOT exit, so the `while true` loop never respawned it. This whole failure class is eliminated by the nginx/systemd setup above (nginx re-resolves the Service endpoint on each request; systemd restarts the container if it ever dies).

## Operational learnings / gotchas (captured 2026-09-29)

### Deploy workflow traps (prod, holken2)
- `./build.sh <tag>` builds the image from the CURRENT WORKING TREE of `~/repo/homedata` on holken2 — it does NOT pull. If you push a fix to origin/WIP from the sandbox, you MUST `git pull` (fast-forward) ON holken2 BEFORE building, or you ship an image without the fix. This bit us: `eds-t65` was built pre-pull (no fix), `eds-t66` was the correct post-pull build. VERIFY after any prod deploy: `kubectl logs <pod> -c eds | grep -iE "migration|reconcile"` and check the expected new markers/files are actually present in the running pod (e.g. `kubectl exec ... ls /homedata/edssensors/migrate_sensorconfig_unique.sql`).
- Image tags are `eds-tNN`, monotonically increasing. As of this session the newest is `eds-t66`. Rollback: `kubectl set image deployments/eds-deployment eds=magnuscj/eds:<prev-tag>`.
- New repo files that the image needs at RUNTIME must be added to the Dockerfile explicitly. Example: `container/migrate_sensorconfig_unique.sql` had to get a `COPY container/migrate_sensorconfig_unique.sql homedata/edssensors` line so createSensorConfig.sh's `$SCRIPT_DIR`-relative lookup resolves. `COPY . homedata` puts the repo at /homedata/... but the shell scripts are separately copied into /homedata/edssensors and run from there (WORKDIR homedata/edssensors), so co-locate runtime deps with the script.

### Prod git auth is BROKEN for fetch/pull from the host
- On holken2, `git fetch/pull` from `git@github.com` fails: `Permission denied (publickey)` — the host has no GitHub-authorized key and there is no ssh-agent. The user pulls by their own means (their workstation / agent). From THIS assistant's shell there is also no ssh-agent (`SSH_AUTH_SOCK` unset, `ssh-add -l` = no agent), so `ssh -A` agent-forwarding to let holken2 auth to GitHub is not available either.
- Consequence: to get code onto prod I cannot `git pull` on holken2 myself. Options are: (a) ask the user to pull+build (what happened), (b) `scp`/copy the specific files holken2->needs then rebuild (leaves prod repo with uncommitted local edits), or (c) set up a GitHub deploy key / start a local agent. There is also a pre-existing local `M ips.txt` modification in the prod repo (pinned ClusterIPs) — expected, don't clobber it.

### kubectl / pod exec facts
- `ssh magnus@192.168.50.171` works via LAN key auth (~/.ssh/id_ed25519). Run all prod kubectl from there (context "minikube"). Sandbox kubectl is local (context "docker-desktop"); note kubectl is NOT on a non-login shell PATH in some cases — it lives at /usr/local/bin/kubectl.
- `kubectl exec <pod> -c eds -- ...` runs as ROOT in the eds pod (uid 0), /tmp is world-writable. BUT leftover files from a prior `kubectl cp` can be owned such that a later write fails ("Permission denied") — use `mktemp` for unique temp paths in scripts/tests, and clean up (`kubectl exec ... rm -f`).
- During a `kubectl rollout restart`, `kubectl get pods` briefly lists BOTH the terminating old pod and the new one; a naive `awk '/^eds-deployment/{print $1}'` returns two names and breaks `kubectl exec`. Filter with `--field-selector=status.phase=Running` and `{print $1; exit}` to get exactly the live pod.

### Testing
- Repo test runner: `./run_tests.sh [cpp|python|shell|integration|all]`. Default runs cpp+python+shell; integration is SANDBOX-ONLY (guarded on kube context == docker-desktop, refuses otherwise). Shell tests stub mysql/mysqldump (tests/shell/stubs) via PATH + STUB_MYSQL_* env vars + overridable STORAGE_DIR. Integration tests hit the real sandbox pod — use throwaway scratch DBs, never mydb, and teardown.
- PHP only runs inside the pod (php8.3); there is no local php. Lint PHP by `kubectl cp` into the pod + `php -l`. To unit-test PHP logic, drive the real file via `php` in the pod against a scratch DB (see tests/integration/test_type_case_normalization.bats).
- MySQL ON DUPLICATE KEY UPDATE ordering matters: assignments evaluate left-to-right and later expressions see already-updated columns. When a fill-only merge guards several columns on a discriminator column, assign that discriminator LAST. Verified against real MySQL 8.0.46 in the pod.

### Data-model reminder reinforced this session
- Canonical FNV sensorids are SHARED prod<->sandbox (e.g. Ute=16261054761367928446, El=10631191365266147712, Heater=12837279452181235050, Pris=7367161076056225378). So a sandbox sensorconfig dump is a valid name/type recovery source for prod by sensorid. `type` values are lowercase canonical: temp, power, moisture, rain, bar, Wind (note: "Wind" is capitalised in existing rows — reports match it as-is), soilmoist, price, default.

## INCIDENT 2026-09-30: crawler wiped sensorconfig via unauthenticated GET on sensorcfg.php

### Summary
Between 02:20 and 04:38 (Europe/Stockholm) on 2026-09-30, a web crawler
(User-Agent `ClaudeBot/1.0 (+claudebot@anthropic.com)`) reached the admin page
`sensorcfg.php` through the PUBLIC port-forward (`http://81.234.147.6:8090/`)
and wiped the prod `sensorconfig` table. The page executed `DELETE`/`UPDATE`
on UNAUTHENTICATED GET requests, and its HTML rendered `?delete=<id>` /
`?edit=<id>` action links — so the crawler mutated the DB simply by following
links. Apache access log showed 532 hits: 354 `?delete=`, 177 `?edit=`, all
ClaudeBot. The collector then re-inserted the still-polled sensorids as fresh
`name`/`default` placeholders, leaving the table at 48 rows / ALL placeholders
/ 0 named. Dashboard tiles (Ute/El/Heater/Pris/…) stopped rendering.

Notably this is the LIKELY cause of the earlier "eds overwrote the sensors"
report too — same mechanism, not the collector.

### Why our prior fixes did NOT prevent it
The sensorconfig invariant work (05ade2d) protects against the COLLECTOR and
the STARTUP RECONCILE demoting names — both held. It does NOT (and cannot)
protect against an authorized-looking SQL DELETE/UPDATE issued by the admin
page itself. This was a separate class of bug: an unauthenticated,
internet-reachable page performing DB mutations via GET. Restoring the external
port-forward the day before is what re-exposed it to the crawler.

### Diagnosis signal (how it was pinned)
- Pod age 12h, RESTARTS 0, same image eds-t66 -> NOT a startup/reconcile issue;
  the wipe happened at RUNTIME while the pod was up.
- `information_schema.tables.UPDATE_TIME` for sensorconfig matched the crawl
  window (04:38).
- `grep sensorcfg /var/log/apache2/access.log` inside the pod showed the
  `?delete=`/`?edit=` GETs and the ClaudeBot UA. That log is the smoking gun —
  check it first for any "data changed itself" report.

### Response (what was done)
1. IMMEDIATE (live pod, ephemeral): wrote
   `/etc/apache2/conf-enabled/zz-block-admin.conf` with `<FilesMatch
   "^(sensorcfg|create_ips)\.php$"> Require all denied </FilesMatch>` +
   `apache2ctl graceful`. sensorcfg.php -> 403, dashboard still 200.
2. RECOVERED names via a single direct multi-row `INSERT ... VALUES ... ON
   DUPLICATE KEY UPDATE` (fill-only, sensorname assigned LAST) using the SEED
   set (canonical FNV sensorids shared prod<->sandbox). Result 54/22/32, correct
   types. Backed up the wiped state first to
   `/tmp/prod_sensorconfig_wiped_*.sql` on holken2.
   - GOTCHA: the multi-STATEMENT heredoc (staging table + INSERT-SELECT) piped
     over `kubectl exec ... mysql` silently did NOT apply the final
     INSERT-SELECT (rc=0 but no effect). The single `-e "INSERT ... VALUES ...
     ODKU"` statement worked reliably. Prefer one direct statement for one-off
     prod data fixes via kubectl exec.
3. Refreshed the PVC seed `/usr/storage/sensorconfig.sql` from the now-healthy
   table so a restart keeps the names.
4. DURABLE code fix (commit 566c769 on origin/WIP, verified in sandbox, deploy
   to prod pending):
   - `sensorcfg.php`: mutations require POST + per-session CSRF token
     (`session_start()`, `bin2hex(random_bytes(32))`, `hash_equals`); Remove is
     a POST form not a GET link; read-only edit view stays GET.
   - `container/apache-admin-deny.conf` baked into the image via Dockerfile
     (conf-enabled) so the deny survives pod restarts.

### Learnings / rules
- ANY page that mutates the DB must NOT do so on GET, must require CSRF, and
  ideally auth. Crawlers follow every link, including destructive ones.
- Exposing a service publicly (port-forward / router NAT) exposes EVERY path it
  serves, including admin pages. Treat the port-forward as internet-facing.
- With `kubectl port-forward`, all requests arrive as `127.0.0.1` and there is
  NO `X-Forwarded-For`. IP-based allow/deny at Apache cannot distinguish LAN
  from external — use a blanket deny for admin pages (or a real auth layer / an
  ingress that sets XFF) rather than an allow-LAN rule.
- Live in-pod Apache edits are EPHEMERAL (lost on pod restart). Any hotfix
  applied with `kubectl exec` must be mirrored into the image/repo to be
  durable. Prod currently relies on the ephemeral block until eds-t67 (with
  566c769) is deployed.
- `create_ips.php` is another unauthenticated admin/mutating page in
  /var/www/html — also denied now. Audit for others before re-exposing.
- Recovery is always possible by sensorid because canonical FNV sensorids are
  shared prod<->sandbox; keep the SEED in createSensorConfig.sh authoritative.

### TODO (open)
- Deploy 566c769 to prod: on holken2 `git pull` (ff to 566c769) then
  `./build.sh eds-t67`; that makes the CSRF + Apache-deny durable and lets the
  ephemeral live-pod block be dropped.
- Still pending from before the incident: externalise secrets (DB password,
  SMTP creds, IPs) out of the repo/image before storing the skill in git
  (recommended: rotate the password, inject via K8s Secret env var, have
  start.sh render config.txt/edsServerHandlerConf.txt at boot, scrub committed
  values). See "deep analysis" from 2026-09-30 session.

## INCIDENT 2026-09-30 (later): holken2 root disk 100% full -> mysqld crash mid eds-t67 deploy

### Summary
While deploying the security fix (commit 566c769) as `eds-t67`, holken2's root
`/dev/sda3` (109G) hit 100% full. Symptoms: `cp: ... eds_deploy.yaml: Enheten
är full`, `git ... index.lock write error. Out of diskspace`, and inside the
new pod `mysqld` was DOWN (`Can't connect ... /var/run/mysqld/mysqld.sock`).
The pod showed 1/1 Running but MySQL had cleanly shut down (`Received SHUTDOWN
from user boot`) because it couldn't write on a full disk.

Root cause: accumulated Docker images. 31 images / 37.48GB, 99% reclaimable —
mainly the eds-tNN build series (t57..t67, ~1.5GB each) plus 12 dangling
(untagged) layers (~15GB). Nothing pruned them; each build added ~1.5GB.

### Recovery
1. `docker rmi magnuscj/eds:eds-t57..t65` + old `test` image -> freed ~14GB
   (kept eds-t67 running + eds-t66 rollback). Then `docker image prune -f`
   removed 12 dangling images -> +15.41GB. Disk 100% -> 74% (28G free).
2. `kubectl rollout restart deployment/eds-deployment` so start.sh re-boots
   mysqld cleanly on the now-free disk. New pod came up 1/1, mysqld+eds+apache2
   up, reconcile ran (migration + restore), sensorconfig healthy 54/22/32.
3. Data was never at risk — mydb is on the Retain PVC; only the mysqld PROCESS
   was down.

### Fix (committed c97fb14)
`build.sh` now, after deploy: keeps the 5 newest `magnuscj/eds:eds-t*` images
(removes older) and runs `docker image prune -f` for dangling layers. Docker
refuses to remove in-use images, so the running pod is always safe.

### GOTCHA: rebuilding an EXISTING tag does NOT roll out
`build.sh <tag>` does `docker build -t magnuscj/eds:<tag>` + push +
`kubectl set image ... eds=magnuscj/eds:<tag>`. If <tag> is the SAME string the
deployment already runs (e.g. re-running `./build.sh eds-t66`), `set image` is a
no-op and NO rollout happens — the pod keeps the old image/code even though a
new image was built locally. ALWAYS bump to a new tag (eds-t67, t68, ...).
Verify a deploy actually took by checking the pod AGE reset + the deployment's
image tag changed, not just that a build ran.

### Liveness analysis (why we did NOT make liveness probe mysqld)
Q: would liveness.sh have caused a cyclic restart on a full disk?
- `container/liveness.sh` only checks `pgrep python3` (hueTemps) and `pgrep eds`
  (collector). It does NOT check mysqld.
- If it did (or if eds/python exited when mysqld died), liveness would fail ->
  kubelet restarts the CONTAINER -> start.sh tries to start mysqld on a STILL-
  FULL disk -> fails again -> CrashLoopBackOff. A restart loop that never
  recovers, and churns logs/writes making disk pressure worse.
- Conclusion: liveness probing mysqld would NOT have prevented the outage (disk
  was the real cause) and would likely have made it worse. Left liveness.sh
  unchanged. Proper mysqld auto-recovery, if ever wanted, is an in-container
  supervisor that restarts mysqld IN PLACE — not a whole-container restart.
- Real preventions: (a) image cleanup in build.sh (done); (b) monitor free disk
  and mysqld liveness OUT-OF-BAND rather than via k8s liveness.

### Remaining disk consumers (2026-09-30, after cleanup: 9 images / 8GB)
In-use app images kept: eds:eds-t67, elprice:elprice-20260927113459,
hue:prod_4, humi:prod_9, rain:prod_10, wind:prod_2. Prunable-later leftovers
seen: hue:prod_2, hue:prod_3 (superseded by prod_4), eds_v2:latest (Jan 2026),
ubuntu:20.04. build.sh only prunes eds-t*; other apps' build scripts
(container/*pod/build_*.sh) do not prune — worth adding the same pattern there.

## Secrets externalisation (DB + SMTP password) — mechanism & ops (2026-10-01)

### What changed (commits 3144008, 2e39f65 on origin/WIP; prod on eds-t68)
The DB password (and SMTP password) used to be hardcoded in ~10 files and thus
in git. They are now injected from a Kubernetes Secret as env vars; nothing
secret remains in the repo working tree or the image. The sandbox + prod DB
passwords were ROTATED to a new value (the old hardcoded literal is now dead;
note it still exists in git HISTORY — rotation makes that harmless, full purge
would need git filter-repo + force-push).

### The Secret
- Name: `eds-db` (namespace default), keys: `db-password`, `smtp-password`.
- NOT in git. Created imperatively PER CLUSTER. Only a `secretKeyRef` is in the
  committed eds.yaml.
- Current values are NOT recorded here (by design). Retrieve from the cluster if
  needed: `kubectl get secret eds-db -o jsonpath='{.data.db-password}' | base64 -d`.
  Convention: db-password is a ~28-char ALPHANUMERIC string (intentionally no
  special chars, to avoid shell/`-p`/mysqldump/URL quoting pitfalls). The SMTP
  password key is `smtp-password`.

### How the password reaches each consumer (single-container pod)
- eds.yaml injects env `DB_PASSWORD` (required) and `SMTP_PASSWORD` (optional) via
  secretKeyRef from `eds-db`.
- container/start.sh at boot: (1) fail-fast if DB_PASSWORD unset; (2)
  `mysql -u root` DROP+CREATE USER dbuser IDENTIFIED BY $DB_PASSWORD (DROP so a
  rotated value takes effect on restart); (3) THEN write /root/.my.cnf
  (user=dbuser, password=$DB_PASSWORD, chmod 600) so later bare mysql/mysqldump
  authenticate without -p; (4) render visualize/config.txt and
  edssensors/edsServerHandlerConf.txt from committed .tmpl files
  (placeholders __DB_PASSWORD__ / __SMTP_PASSWORD__).
- Consumers: PHP dashboards read config.txt via getConfig() (unchanged); C++
  collector reads edsServerHandlerConf.txt (unchanged); backup.sh uses ~/.my.cnf
  (no -p); sensorcfg.php uses getenv('DB_PASSWORD') + MYSQL_PWD for mysqldump;
  eds_web.py / getElectricityPrices.py read os.environ['DB_PASSWORD'].
- Committed config.txt / edsServerHandlerConf.txt carry PLACEHOLDERS (regenerated
  at runtime). cpp test fixture uses a dummy; integration test probe reads env.

### ORDERING GOTCHA (cost a redeploy)
~/.my.cnf sets user=dbuser. If written BEFORE `CREATE USER`, every subsequent
bare `mysql` (the CREATE USER heredoc itself, restore.sh, createSensorConfig.sh)
tries to auth as the not-yet-existent dbuser -> `Access denied (using password:
YES)` and cascading failures. FIX: CREATE USER must run as `mysql -u root`, and
~/.my.cnf must be written AFTER the user exists. (start.sh now does this.)

### CRITICAL deploy requirement — create the Secret BEFORE the new pod
Because start.sh fail-fasts on missing DB_PASSWORD, the `eds-db` Secret MUST
exist in a cluster before an env-wired pod starts, or it CrashLoopBackOffs
(`FATAL: DB_PASSWORD env is not set`). Bootstrap per cluster:
```bash
kubectl create secret generic eds-db \
  --from-literal=db-password='<pw>' \
  --from-literal=smtp-password='<smtp_pw>' \
  --dry-run=client -o yaml | kubectl apply -f -
```
Recreate it if a cluster is rebuilt. K8s Secret is only base64 in etcd (not
encrypted at rest unless EncryptionConfiguration is set) — acceptable on this
home LAN, far better than git. No external dependency.

### ROTATING the password
1. Replace the Secret (same `kubectl create ... --dry-run | apply` form).
2. `kubectl rollout restart deployment/eds-deployment` — start.sh DROP+CREATEs
   the MySQL user with the new value and re-renders config. No image rebuild
   needed just to rotate. Verify: `kubectl exec <pod> -c eds -- mysql -e 'SELECT 1;'`.

### build.sh BUG found & fixed (commit 2e39f65) — set image != apply
build.sh's existing-deployment branch only ran `kubectl set image` and
regenerated eds_deploy.yaml WITHOUT applying it. So any eds.yaml change beyond
the image tag (env/secretKeyRef, volumes, probes) silently never reached the
cluster. First eds-t68 deploy crash-looped on missing DB_PASSWORD for exactly
this reason; fixed by having the branch `kubectl apply -f eds_deploy.yaml`.
RULE: when a deploy adds/changes pod spec (not just the image), ensure the full
manifest is applied, and verify with
`kubectl get deployment eds-deployment -o jsonpath='{.spec.template.spec.containers[0].env}'`.

### Prod deploy of this work (done 2026-10-01)
Created eds-db Secret on holken2 (minikube) -> user pulled WIP to 3144008 ->
`./build.sh eds-t68` -> first pod crash-looped (set-image-only, no env) ->
`kubectl apply -f eds_deploy.yaml` fixed it -> eds-t68 healthy: dbuser auth via
Secret, reconcile ran, sensorconfig 54/22/32, sensorcfg.php 403, dashboard 200.
Old eds-t67 kept serving throughout (no outage). Prod image series now t66/t67/t68.

### Still open
- SMTP password only moved to the Secret for the collector conf; audit whether
  any other component hardcodes smtp creds (communication.cc reads them from the
  conf, which is now templated — OK).
- IPs not yet externalised (lower sensitivity; same template pattern would work).
- Now that the repo tree is clean of the literal, the skill itself can be stored
  in git — but scrub the current-password line from THIS skill first (it names
  the live value) if the repo is public.

## eds collector supervision (2026-10-03, commit a50caec)

### Problem
The eds collector (`./eds`) was launched ONCE in the background by
`container/start.sh` with no supervisor, then start.sh blocked in the foreground
on `hueTemps.py`. If the collector's initial launch didn't survive (it starts
right after createSensorConfig while mysqld may still be warming up, or it exits
for any reason) NOTHING restarted it — the pod ran 1/1 Running but collected no
data until someone ran `start_eds.sh` by hand. Observed on the eds-t71 deploy:
fresh pod, `pgrep -x eds` empty, latest measurement ~30 min stale; a manual
`start_eds.sh` fixed it permanently (so the binary is fine — the issue was the
unsupervised single-shot launch). The liveness probe (`pgrep eds`) only triggers
a whole-CONTAINER restart, which re-runs the same fragile launch and does not
reliably converge.

### Fix (shipped)
- `edssensors/supervise_eds.sh` (new): waits for `mysql -e "SELECT 1"` to
  succeed, then runs `./eds $(< /usr/storage/ips/ips.txt)` in the loop
  foreground and respawns it after 5s whenever it exits. Logs to
  /tmp/eds_output.txt (`supervise_eds: ...`).
- `container/start.sh`: runs `./supervise_eds.sh &` instead of the old one-shot
  `./start_eds.sh`.
- `edssensors/start_eds.sh`: now a safe single-shot RESTART — it kills the
  running collector (guarded pgrep, no more `kill: usage` noise) and lets the
  supervisor respawn it; used by create_ips.php (`nohup sudo start_eds.sh &`)
  and manual restarts. Falls back to a one-shot `./eds &` only if no supervisor
  is running.
- Dockerfile: `chmod +x supervise_eds.sh`.
- Verified in sandbox: collector auto-starts on a fresh pod; `kill -9 $(pgrep -x
  eds)` -> respawn in ~5s (log shows rc=137 then relaunch); manual start_eds.sh
  leaves exactly ONE eds process (no duplicate); data keeps flowing.

### DEFERRED (not yet fixed): connectToDatabase() retry bug in edsServerHandler.cc
`connectToDatabase()` retries `mysql_real_connect` 10x with 1s sleeps, but on a
FAILED connect `mysql_real_connect` returns NULL and OVERWRITES `dbConnection`
with NULL — so the earlier `mysql_init` handle is lost and subsequent retries
are ineffective; after 10 failures it just prints "Mysql error: could not
connect to database" and returns with a NULL connection (collector then runs
without a working DB). The supervisor loop masks this (it waits for mysqld and
respawns), but the C++ retry should be fixed to re-init on each attempt and/or
exit non-zero on total failure so the supervisor restarts it cleanly. Needs a
C++ change + rebuild + test — do later.

## Session update (2026-10-23/25): PHP security patch, absolute WORKDIR, and sensor-pod base-image migration to Ubuntu 24.04

### PHP: keeping the eds pod on the latest 8.3.x security patch (commit e5fc5ce)
- eds base is `ubuntu:24.04`, which ships PHP as the `php8.3` apt packages.
  `php -v` ALWAYS prints `8.3.6` — Ubuntu keeps the upstream version string
  fixed and backports security fixes into the revision suffix
  `-0ubuntu0.24.04.NN`. The real "is it patched" signal is that NN, not the
  8.3.6 label. (Observed: .11 -> .13 was the patch gap on 2026-10-23.)
- Dockerfile change: after the big `apt-get install`, added
  `&& apt-get -y update && apt-get -y install --only-upgrade php8.3 php8.3-cli
  php8.3-common php8.3-mysql php8.3-mbstring php8.3-gd`. Used
  `install --only-upgrade` NOT plain `apt-get upgrade`: `upgrade` HELD the php
  packages back at .11 because the bump needed newer deps (php8.3-common,
  libapache2-mod-php8.3); `install --only-upgrade` pulls those deps and lands
  .13. Also bumped the committed `ARG CACHE_DATE` default (cosmetic — build.sh
  and deploy_local.sh both pass `--build-arg CACHE_DATE=<timestamp>` which
  overrides it and always busts the apt layer anyway).
- VERIFY a patch actually landed (don't trust `php -v`): inside the pod
  `dpkg -l | grep '^ii' | grep php8.3` shows the `...24.04.NN` revision, and
  `apt-get -s install --only-upgrade php8.3 ...` should say `0 upgraded`.
- CVEs closed by .11 -> .13 (read from
  `/usr/share/doc/php8.3-common/changelog.Debian.gz` in the pod — authoritative,
  no guessing): .13 = CVE-2026-109618 / CVE-2026-109619 (Zend engine state-
  corruption RCE). .12 = CVE-2026-92842, CVE-2026-91768, CVE-2026-91769,
  CVE-2026-91767, CVE-2025-1218 (mysqlnd OOB — in our path), CVE-2026-91766 +
  CVE-2026-93682 (HTTP stream wrapper — relevant: pod uses
  file_get_contents("http://...") with allow_url_fopen=1), CVE-2026-91765 (SOAP
  DoS), CVE-2025-14181 (SOAP heap overflow), CVE-2026-6103 (phar TAR int
  overflow). The changelog is the quick way to answer "which CVEs" for any
  Ubuntu package revision.
- Deployed: sandbox via `./deploy_local.sh`; prod as `eds-t74`. Prod pull is
  still broken on holken2 (Permission denied publickey), so the committed
  Dockerfile was `scp`'d holken2:~/repo/homedata then `./build.sh eds-t74` run
  there — leaves the prod repo with a local Dockerfile edit identical to the
  committed one (git checkout -- Dockerfile after a future pull, or it ff-s).

### Absolute WORKDIR (commit f2149ff) — silences BuildKit WorkdirRelativePath warning
- Only TWO Dockerfiles had an ACTIVE relative WORKDIR (the ones that warned):
  main `Dockerfile` (`WORKDIR homedata/edssensors` -> `/homedata/edssensors`)
  and `container/potpod/Dockerfile` (`-> /homedata/container/potpod`). Base
  image default WORKDIR is `/` and code lives at `/homedata`, so absolute paths
  resolve identically — zero behavioural change. The other pods' WORKDIR lines
  are COMMENTED OUT (`#WORKDIR ...`) so they never warned; left untouched.

### Sensor-pod base-image migration ubuntu:20.04 -> ubuntu:24.04 (commits 4d5ee98 + follow-up)
Why: Ubuntu 20.04 LTS is EOL (standard support ended Apr 2025; ESM only). The
five sensor pods (hue, humi, rain, wind, elprice) plus potpod were all still
`FROM ubuntu:20.04`, carrying many pending security updates (wind 92, humi 46,
etc.). The eds pod was already on 24.04 and fully patched (0 upgradable).

KEY DISCOVERY — the sensor pods DO NOT USE PHP at all. hue/humi/rain/wind
installed `php7.4*` but there are NO .php files and no php invocation anywhere;
their runtime is purely `python3 <script>.py` + apache2 (static XML) + cron +
ssh (see each `start.sh`). `php7.4` does not exist in 24.04's repos, so a naive
FROM bump fails on the php lines — the fix is to DELETE the unused php7.4*
packages (not port them to php8.3). elprice/potpod never had php.

Per-Dockerfile changes applied:
- All: `FROM ubuntu:20.04` -> `ubuntu:24.04`; add `ENV DEBIAN_FRONTEND=noninteractive`
  (24.04's mysql-server/tzdata installs can prompt and hang a non-interactive build).
- hue, humi, rain, wind: REMOVE the six `php7.4*` packages.
- hue, humi: ADD `python3-requests` — their scripts (`huesensors.py`, `humi.py`)
  `import requests` but it was NEVER installed (latent bug on 20.04 too). rain/
  wind/elprice use stdlib urllib only, so no requests needed.
- wind: keep `apache2` (it's the only sensor pod that genuinely serves via apache
  besides elprice). elprice: python3 + apache2, no mysql.
- Python jumps 3.8 -> 3.12.3; all scripts are plain stdlib+requests, no 3.12
  breakage. details.xml generation verified working on every pod.

Deploy/verify mechanism: `container/build_pod.sh <app>` (app = hue|humi|rain|
wind|elprice) does Docker Hub preflight -> build -> push -> apply full manifest
-> rollout -> prune to newest-5. potpod is NOT supported by build_pod.sh and has
NO deployment (its ENTRYPOINT is commented out) — it was only build-verified.
GOTCHA when verifying a rollout: `kubectl get pods --field-selector=status.phase
=Running` can still return the OLD pod mid-termination; select the NEW pod by
IMAGE TAG, e.g. `... -o jsonpath=... | awk '/<app>-deployment.*<newtag>/{print $1}'`.

Status after this session: SANDBOX (docker-desktop) — all of hue/humi/rain/wind/
elprice rebuilt on 24.04.5, Python 3.12.3, collectors running, details.xml (and
elprice's electricityprice.csv) updating; potpod build-only verified. Commits on
origin/WIP: 4d5ee98 (huepod) committed+pushed; the other five Dockerfiles
(humipod, rainpod, windpod, elpricepod, potpod) were edited and sandbox-verified
— commit/push them as a group next. PROD (holken2) sensor pods NOT yet migrated
(still on their prod_N 20.04 images); migrating prod is a separate step and prod
hue/humi/rain/wind run different image tags than sandbox.

GENERAL GOTCHA (cost ~hours this session): a plain `docker run <podimage>` HANGS
because every pod's ENTRYPOINT is `start.sh`, a foreground service loop (sshd -D
/ python while-loop). To smoke-test an image non-interactively use
`docker run --rm --entrypoint bash <image> -c '...'`. A cancelled `docker run`
can also leave a stray container holding the image (blocks `docker rmi ...:tag`);
`docker rm -f <id>` it first. Prefer deploying to the sandbox cluster + `kubectl
exec` for verification over `docker run`.

### CORRECTION (2026-10-25): external public access port changed 8181 -> 8090 (router)
Supersedes the 2026-09-29 "EXTERNAL public access" section above for the PORT
only. User changed the ROUTER WAN NAT rule to forward to holken2 LAN
`192.168.50.171:8090` (previously `:8181`). The public URL
`http://81.234.147.6:8090/android_F_S_dynamic.html` is unchanged externally
(was already :8090 on the WAN side) but now lands on holken2 :8090.
- CONFIRMED 2026-10-25: public URL -> 200; `0.0.0.0:8090` is LISTENing on
  holken2 and serves the dashboard (local curl -> 200). The old
  `kubectl port-forward --address 192.168.50.171 ... 8181:80` (PID 147057,
  since Oct 23) is STILL running and still answers on :8181, but the router no
  longer targets it — so the 8181 forwarder is now OUT of the external path and
  effectively redundant (safe to retire when convenient).
- OPEN ITEM: the process bound to `0.0.0.0:8090` was NOT identified from this
  assistant's shell (`sudo ss -tlnp` needs an interactive tty over non-login
  ssh). Note it binds `0.0.0.0` (not `--address 192.168.50.171`) with backlog
  511 — i.e. NOT the documented kubectl forwarder pattern (those use
  --address + backlog 4096). Likely a reverse proxy (nginx/caddy/apache/socat)
  or a different forwarder the user started. IDENTIFY + document the exact
  mechanism before relying on the FRAGILE/zombie failure-mode recipe above,
  which is specific to `kubectl port-forward`. If :8090 is a kubectl forwarder,
  the same half-dead-after-rollout failure mode and keep-alive-loop hardening
  notes apply; if it is a real reverse proxy in front of the NodePort/service,
  it is likely more robust and the old 8181 fragility notes do NOT apply.
- Diagnosis recipe from the 2026-09-29 section still applies with 8181 -> 8090
  substituted for the LAN-port checks.
