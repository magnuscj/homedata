# homedata test suite

Structured, tiered tests for the homedata sensor stack. Built bottom-up: fast
pure-logic unit tests first, live sandbox smoke tests last.

## Layout

```
tests/
├── cpp/            C++ unit tests (Catch2, header-only, vendored)
│   ├── catch.hpp               vendored Catch2 v2 single header (no system pkg)
│   ├── Makefile                builds tests against ../../edssensors objects
│   ├── fixtures/               details.xml + conf used by decodeXml tests
│   ├── test_stablehash.cc      FNV-1a stableHash()
│   └── test_decode_xml.cc      decodeXml() parsing + canonical sensorid
├── python/         Python unit tests (pytest)
│   ├── conftest.py             loads pod modules by path (no main() side effects)
│   ├── requirements.txt        pinned pytest
│   ├── test_rain.py            rain parse_rain_value / template substitution
│   ├── test_wind.py            wind parse_sensor_data / template substitution
│   ├── test_humi.py            humi ch_soil template substitution
│   ├── test_hue.py             hue create_details (requests.get mocked)
│   └── test_eds_web.py         eds_web to_html + ANSI/frame handling
├── shell/          Shell tests (bats) with stubbed mysql/mysqldump/tar
│   ├── stubs/                  fake mysql / mysqldump on PATH during tests
│   ├── test_backup.bats        row-count gate + archive rotation
│   ├── test_restore.bats       newest-valid selection + corrupt fallback
│   └── test_createsensorconfig.bats  reseed-when-no-named-rows fallback
├── integration/    Live SANDBOX pod/K8s smoke tests (bats)
│   └── test_pods.bats          all 5 pods + optional throwaway eds pod
└── .venv/          project-local venv holding pytest (gitignored)
```

The single runner is `../run_tests.sh`.

## Running

```bash
./run_tests.sh              # cpp + python + shell (default; no integration)
./run_tests.sh cpp          # one tier
./run_tests.sh python
./run_tests.sh shell
./run_tests.sh integration                    # sandbox smoke tests
./run_tests.sh integration --with-throwaway   # + deploy/test/teardown a temp eds pod
./run_tests.sh all          # every tier in order, unified summary + exit code
```

A tier whose tooling is missing is **skipped, not failed** — the preflight table
at the top shows what's available. `all` runs `cpp → python → shell →
integration` and exits non-zero if any non-skipped tier fails.

## Required tooling

| Tier        | Needs                                   | Install hint |
|-------------|-----------------------------------------|--------------|
| cpp         | `g++`, `mysql_config`, `libcurl`, tinyxml2 at `../../tinyxml2` | system pkgs; `catch.hpp` is vendored |
| python      | `pytest` (project venv preferred)       | `python3 -m venv tests/.venv && tests/.venv/bin/pip install -r tests/python/requirements.txt` |
| shell       | `bats`                                  | `apt install bats` |
| integration | `kubectl` + `docker-desktop` context    | Docker Desktop Kubernetes |

The runner auto-uses `tests/.venv` for pytest if present, else falls back to
system `python3`.

## Safety — SANDBOX ONLY, never prod

The integration tier **only ever runs against the `docker-desktop` kube
context** (the sandbox on this machine). This is enforced in two places:

1. `run_tests.sh` checks `kubectl config current-context == docker-desktop` and
   skips the tier otherwise.
2. `test_pods.bats` `setup()` re-checks the context and `skip`s if it isn't the
   sandbox — defence in depth, so running the file directly with `bats` can't
   hit prod (holken2 / minikube) either.

All integration checks are **read-only** by default. The one mutating path — a
throwaway eds pod — is gated behind `--with-throwaway` / `EDS_WITH_THROWAWAY=true`
and always tears itself down (even on failure).

Sandbox NodePorts (differ from prod): eds=30164, humi=30165, wind=30166,
hue=30167, rain=30168.

## Notes

- The migration scripts (`add_id2.py`, `swap_sensorid_id2.py`,
  `repair_sensorconfig_dupes.py`, `rekey_measurements.py`) are intentionally
  **not** covered — they are being removed.
- `tests/cpp/test_decode_xml.cc` supersedes the old
  `edssensors/test_decodeXml.cc` (same assertions, now under Catch2).
- To enable testability, `container/{backup,restore,createSensorConfig}.sh`
  gained an overridable `STORAGE_DIR` (defaults to `/usr/storage`, so production
  behaviour is unchanged).
```
