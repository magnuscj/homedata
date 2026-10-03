# Runbook: rotate the DB password & deploy the eds pod set

Audience: a human operator. Covers (1) rotating the database password and
(2) deploying / bootstrapping the eds stack in a cluster — whether that cluster
is **prod (minikube on holken2)** or **sandbox (Docker Desktop on this machine)**.

---

## Background you need (read once)

- The eds pod runs MySQL, Apache/PHP, and the C++ collector in **one container**.
- The database password is **not** stored in the code or the image. It lives in
  a Kubernetes **Secret** named `eds-db` and is injected into the pod as the
  environment variable `DB_PASSWORD`.
- When the pod starts, `start.sh`:
  1. refuses to start if `DB_PASSWORD` is missing,
  2. (re)creates the MySQL user `dbuser` with that password,
  3. writes `/root/.my.cnf` so other tools authenticate without a password on
     the command line,
  4. fills the password into `config.txt` (PHP) and `edsServerHandlerConf.txt`
     (collector) from template files.
- Because the user is **re-created on every start**, rotating the password is
  just: update the Secret, restart the pod.
- The database data itself lives on a persistent volume (PVC, `Retain`) and is
  **not** affected by changing the password or restarting the pod.

### Which cluster am I on?

Always check before running anything:

```bash
kubectl config current-context
```

- `minikube`        → **PRODUCTION** (host holken2, reach via `ssh magnus@192.168.50.171`)
- `docker-desktop`  → **SANDBOX** (this machine / WSL)

The two clusters are completely separate: separate databases, separate Secrets.
Their passwords may be the same or different — your choice.

---

## Part 1 — Rotate the database password

Do this in whichever cluster you intend to change. To rotate both, do the whole
procedure once per cluster.

### Step 1: choose a new password

Use a strong value. **Prefer letters and digits only** (no `#`, `!`, `$`, `/`,
quotes, etc.). Special characters have repeatedly caused shell/escaping bugs in
this stack. Example of generating one:

```bash
LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 28; echo
```

Copy the value somewhere safe (a password manager). You will not be able to read
it back out easily later except from the cluster (see "Verify" below).

### Step 2: update the `eds-db` Secret

This single command creates the Secret if it is missing, or replaces it if it
already exists. Replace the two values with your real ones. Keep the SMTP value
as whatever it currently is unless you are also rotating that.

```bash
kubectl create secret generic eds-db \
  --from-literal=db-password='PUT_NEW_PASSWORD_HERE' \
  --from-literal=smtp-password='PUT_SMTP_PASSWORD_HERE' \
  --dry-run=client -o yaml | kubectl apply -f -
```

> On production, run this on holken2: `ssh magnus@192.168.50.171` first, then
> the command (its kube context is already `minikube`).

### Step 3: restart the pod so it picks up the new password

```bash
kubectl rollout restart deployment/eds-deployment
kubectl rollout status  deployment/eds-deployment --timeout=240s
```

`start.sh` will drop and recreate the MySQL `dbuser` with the new password and
re-render the config files. No image rebuild is needed just to rotate.

### Step 4: verify

```bash
POD=$(kubectl get pods --no-headers --field-selector=status.phase=Running \
      | awk '/^eds-deployment/{print $1; exit}')

# DB auth works with the new password (via /root/.my.cnf):
kubectl exec "$POD" -c eds -- mysql -e "SELECT 1 AS ok;"      # expect: 1

# Dashboard still serves, admin page still blocked:
kubectl exec "$POD" -c eds -- bash -c \
  "curl -s -o /dev/null -w 'dashboard=%{http_code} admin=' http://localhost/android_F_S_dynamic.html; \
   curl -s -o /dev/null -w '%{http_code}\n' http://localhost/sensorcfg.php"
# expect: dashboard=200 admin=403
```

To read the password currently stored in the Secret (e.g. if you forgot it):

```bash
kubectl get secret eds-db -o jsonpath='{.data.db-password}' | base64 -d; echo
```

---

## Part 2 — Deploy / bootstrap the eds pod set in a cluster

Use this when setting up a **new or rebuilt cluster**, or deploying a new image.
The key rule: **the `eds-db` Secret must exist BEFORE the pod starts**, or the
pod will crash-loop with `FATAL: DB_PASSWORD env is not set`.

### Step 0: confirm the cluster

```bash
kubectl config current-context   # minikube (prod) or docker-desktop (sandbox)
```

### Step 1: create the Secret (if it does not already exist)

```bash
kubectl get secret eds-db >/dev/null 2>&1 \
  && echo "Secret already exists" \
  || kubectl create secret generic eds-db \
       --from-literal=db-password='PUT_PASSWORD_HERE' \
       --from-literal=smtp-password='PUT_SMTP_PASSWORD_HERE' \
       --dry-run=client -o yaml | kubectl apply -f -
```

### Step 2a: deploy on PRODUCTION (minikube, holken2)

Run on holken2 (`ssh magnus@192.168.50.171`), from `~/repo/homedata`:

```bash
cd ~/repo/homedata
git pull                      # get the latest WIP (code + eds.yaml)
./build.sh eds-tNN            # NN = next unused number, e.g. eds-t69
```

- **Always use a NEW tag.** Re-using the current tag does not trigger a rollout.
- `build.sh` builds, pushes, **applies the full manifest** (image + env/Secret
  wiring), and prunes old images to protect disk space.

Watch it come up:

```bash
kubectl rollout status deployment/eds-deployment --timeout=240s
```

### Step 2b: deploy on SANDBOX (Docker Desktop, this machine)

From the repo on this machine (`/home/magnus/homedata`):

```bash
./deploy_local.sh             # builds magnuscj/eds:local-<timestamp>, applies, restarts
```

### Step 3: verify the deploy

```bash
POD=$(kubectl get pods --no-headers --field-selector=status.phase=Running \
      | awk '/^eds-deployment/{print $1; exit}')
echo "pod=$POD image=$(kubectl get pod $POD -o jsonpath='{.spec.containers[0].image}')"

# All core processes up:
kubectl exec "$POD" -c eds -- bash -c \
  'for p in mysqld eds apache2; do pgrep -x $p >/dev/null && echo $p:up || echo $p:DOWN; done'

# DB auth + data present:
kubectl exec "$POD" -c eds -- mysql -N -e \
  "SELECT COUNT(*) FROM mydb.sensorconfig;"

# Startup reconcile ran without fatal errors:
kubectl logs "$POD" -c eds | grep -iE "FATAL|reconcile complete" | head
```

Expected: processes `up`, a row count > 0, a `reconcile complete` line, and no
`FATAL`.

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Pod `CrashLoopBackOff`, logs `FATAL: DB_PASSWORD env is not set` | Secret missing, or deploy applied image only (not the env wiring) | Create the `eds-db` Secret (Part 2 Step 1); ensure the full manifest was applied: `kubectl apply -f eds_deploy.yaml`. Check: `kubectl get deployment eds-deployment -o jsonpath='{.spec.template.spec.containers[0].env}'` should list `DB_PASSWORD`. |
| `mysql` says `Access denied for user 'dbuser' ... (using password: YES)` | Secret value and MySQL user out of sync (e.g. Secret changed but pod not restarted) | `kubectl rollout restart deployment/eds-deployment` |
| Deploy "ran" but the running pod still has old behaviour | Re-used an existing image tag → no rollout | Rebuild with a NEW tag (`eds-t<NN+1>`) |
| Pod won't schedule / build fails with "out of disk space" (prod) | holken2 root disk full from old images | `docker image prune -f`; keep only recent `eds-t*` tags (newer `build.sh` does this automatically) |
| `dashboard` returns 000 externally but 200 inside the pod | the host `kubectl port-forward` (LAN:8181) died/zombied after a pod restart | restart the port-forward loop on holken2 (binds `--address 192.168.50.171 ... 8181:80`) |

---

## Safety notes

- The database (`mydb`) is on a `Retain` PVC; rotating the password or restarting
  the pod does **not** delete data.
- `sensorcfg.php` (the admin page) is intentionally blocked (HTTP 403) and
  requires POST + CSRF for changes — do not "fix" it by re-enabling GET writes.
- Never put the password into git, `eds.yaml`, or an image. Only the Secret
  (`eds-db`) and the running pod's ephemeral files hold the real value.
