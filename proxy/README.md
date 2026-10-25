# eds edge reverse proxy

Permanent setup that recovers and logs the **real client IP** of visitors to the
eds pod, replacing the `kubectl port-forward` hop that hid every visitor behind
`127.0.0.1`.

## Why this exists

The eds pod runs on minikube-on-Docker. The node IP (`192.168.49.2`) is not
reachable from the LAN router, so external traffic used to arrive through
`port_forward.sh` (`kubectl port-forward`). That is an L4 loopback tunnel: it
cannot carry the client IP or add headers, so Apache in the pod logged every
request as `127.0.0.1`. A NodePort alone doesn't help either — with
`externalTrafficPolicy: Cluster` the source IP is SNAT'd away.

The fix is an HTTP-aware hop at the host edge, where the router NAT still
delivers the real client IP:

```
client -> TUF-AX5400 router NAT :8090
       -> nginx on host  (logs real IP, filters internal, adds X-Forwarded-For)
       -> 192.168.49.2:30164  (eds NodePort)
       -> eds pod :80 (Apache)
```

The host can reach the NodePort directly, so **no `kubectl port-forward` is
needed** — `port_forward.sh` is superseded.

## Files

| File | Purpose |
|------|---------|
| `nginx-eds.conf`    | Reverse proxy, real-IP access log, internal-IP filter |
| `eds-proxy.service` | systemd unit running a pinned nginx container, restart=always |
| `logrotate-eds`     | Size-capped (50M), 14-day, compressed log rotation |
| `install.sh`        | Idempotent installer (config + systemd + logrotate) |

## Install

```bash
sudo proxy/install.sh
# then point the router's :8090 forward at THIS host's :8090
```

## Logging behaviour

- Access log: `/home/magnus/eds-logs/eds_access.log` (real external IPs only).
- Internal/LAN addresses (loopback, `10.x`, `192.168.x`, `172.16-31.x`,
  `169.254.x`) are filtered out via an nginx `map` + conditional `access_log`.
- Rotation: daily, keep 14, force-rotate at 50M, gzip old files
  (`copytruncate` so nginx needs no signal).
- The container's own stdout/stderr is capped via `--log-opt max-size/max-file`.

## Operate

```bash
systemctl status eds-proxy
systemctl restart eds-proxy          # after editing /etc/eds-proxy/nginx-eds.conf
journalctl -u eds-proxy -f
tail -f /home/magnus/eds-logs/eds_access.log
```

## Notes / tradeoffs

- The eds pod and its Apache are **unchanged**; all IP handling lives at the
  edge. If you later expose the pod so the node is LAN-reachable (e.g.
  `minikube tunnel`), you could set the Service to
  `externalTrafficPolicy: Local` and drop this proxy — but that is a bigger
  networking change.
- nginx is pinned to `nginx:1.27-alpine` in the unit; bump deliberately.
- GDPR: client IPs are personal data. Retention is capped at 14 rotations by
  logrotate; adjust `rotate`/`maxsize` in `logrotate-eds` to your policy, and
  disclose logging if you have EU visitors.
