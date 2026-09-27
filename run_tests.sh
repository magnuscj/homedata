#!/usr/bin/env bash
#
# run_tests.sh — unified test runner for the homedata sensor stack.
#
# Tiers (built bottom-up, simplest first):
#   cpp          C++ unit tests (Catch2)          — stableHash, decodeXml
#   python       Python unit tests (pytest)       — pod collectors, eds_web
#   shell        Shell tests (bats)               — backup/restore/seed logic
#   integration  Live sandbox pod/K8s smoke tests — SANDBOX ONLY (docker-desktop)
#   all          run cpp -> python -> shell -> integration in order
#
# Usage:
#   ./run_tests.sh                 # default: cpp + python + shell (no integration)
#   ./run_tests.sh cpp             # a single tier
#   ./run_tests.sh all             # every tier, including integration
#   ./run_tests.sh integration --with-throwaway
#
# A tier whose required tooling is missing is SKIPPED (not failed), with an
# install hint. Integration NEVER runs against prod: it verifies the kube
# context is 'docker-desktop' first and skips otherwise.

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TESTS="$ROOT/tests"

GREEN='\033[0;32m'; RED='\033[0;31m'; YELLOW='\033[1;33m'; DIM='\033[2m'; NC='\033[0m'

# ---- tool detection ---------------------------------------------------------
have() { command -v "$1" >/dev/null 2>&1; }

HAVE_GPP=false;    have g++         && HAVE_GPP=true
HAVE_PY=false;     have python3     && HAVE_PY=true
# Prefer a project-local venv (tests/.venv) if it has pytest; else fall back to system python3.
PYTHON_BIN="python3"
if [[ -x "$TESTS/.venv/bin/python" ]] && "$TESTS/.venv/bin/python" -c 'import pytest' 2>/dev/null; then
  PYTHON_BIN="$TESTS/.venv/bin/python"
fi
HAVE_PYTEST=false; "$PYTHON_BIN" -c 'import pytest' 2>/dev/null && HAVE_PYTEST=true
HAVE_BATS=false;   have bats        && HAVE_BATS=true
HAVE_KUBECTL=false;have kubectl     && HAVE_KUBECTL=true
KUBE_CTX=""; $HAVE_KUBECTL && KUBE_CTX="$(kubectl config current-context 2>/dev/null)"
HAVE_CATCH=false;  [[ -s "$TESTS/cpp/catch.hpp" ]] && HAVE_CATCH=true

preflight() {
  echo -e "${DIM}--- preflight ---${NC}"
  printf "  %-14s %s\n" "g++"     "$($HAVE_GPP    && echo OK || echo 'MISSING (apt install g++)')"
  printf "  %-14s %s\n" "python3" "$($HAVE_PY     && echo OK || echo 'MISSING (apt install python3)')"
  printf "  %-14s %s\n" "pytest"  "$($HAVE_PYTEST && echo OK || echo 'MISSING (pip install pytest  OR  apt install python3-pytest)')"
  printf "  %-14s %s\n" "bats"    "$($HAVE_BATS   && echo OK || echo 'MISSING (apt install bats)')"
  printf "  %-14s %s\n" "catch.hpp" "$($HAVE_CATCH && echo OK || echo 'MISSING (see tests/README.md)')"
  printf "  %-14s %s\n" "kube ctx" "${KUBE_CTX:-none}"
  echo
}

# ---- tier result tracking ---------------------------------------------------
TIER_NAMES=(); TIER_RESULTS=()   # result: PASS / FAIL / SKIP
record() { TIER_NAMES+=("$1"); TIER_RESULTS+=("$2"); }

# ---- tiers ------------------------------------------------------------------
run_cpp() {
  echo -e "${DIM}=== tier: cpp ===${NC}"
  if ! $HAVE_GPP || ! $HAVE_CATCH; then
    echo -e "${YELLOW}[SKIP]${NC} cpp (need g++ and tests/cpp/catch.hpp)"; record cpp SKIP; return
  fi
  if ! ls "$TESTS"/cpp/test_*.cc >/dev/null 2>&1; then
    echo -e "${YELLOW}[skip]${NC} no C++ tests yet"; record cpp SKIP; return
  fi
  if make -C "$TESTS/cpp" test; then
    echo -e "${GREEN}[PASS]${NC} cpp"; record cpp PASS
  else
    echo -e "${RED}[FAIL]${NC} cpp"; record cpp FAIL
  fi
}

run_python() {
  echo -e "${DIM}=== tier: python ===${NC}"
  if ! $HAVE_PYTEST; then
    echo -e "${YELLOW}[SKIP]${NC} python (pytest missing)"; record python SKIP; return
  fi
  if ! ls "$TESTS"/python/test_*.py >/dev/null 2>&1; then
    echo -e "${YELLOW}[skip]${NC} no Python tests yet"; record python SKIP; return
  fi
  # Hard wall-clock cap so a misbehaving import (e.g. an unguarded module-level
  # loop) can never hang the tier. `timeout` returns 124 on expiry.
  PYTEST_CAP="${PYTEST_CAP:-120}"
  if timeout "$PYTEST_CAP" "$PYTHON_BIN" -m pytest "$TESTS/python" -q -p no:cacheprovider; then
    echo -e "${GREEN}[PASS]${NC} python"; record python PASS
  else
    rc=$?
    if [[ $rc -eq 124 ]]; then
      echo -e "${RED}[FAIL]${NC} python (timed out after ${PYTEST_CAP}s — likely a module running code on import)"
    else
      echo -e "${RED}[FAIL]${NC} python"
    fi
    record python FAIL
  fi
}

run_shell() {
  echo -e "${DIM}=== tier: shell ===${NC}"
  if ! $HAVE_BATS; then
    echo -e "${YELLOW}[SKIP]${NC} shell (bats missing)"; record shell SKIP; return
  fi
  if ! ls "$TESTS"/shell/*.bats >/dev/null 2>&1; then
    echo -e "${YELLOW}[skip]${NC} no shell tests yet"; record shell SKIP; return
  fi
  if bats "$TESTS"/shell/*.bats; then
    echo -e "${GREEN}[PASS]${NC} shell"; record shell PASS
  else
    echo -e "${RED}[FAIL]${NC} shell"; record shell FAIL
  fi
}

run_integration() {
  echo -e "${DIM}=== tier: integration (SANDBOX ONLY) ===${NC}"
  if ! $HAVE_BATS; then
    echo -e "${YELLOW}[SKIP]${NC} integration (bats missing)"; record integration SKIP; return
  fi
  if ! $HAVE_KUBECTL; then
    echo -e "${YELLOW}[SKIP]${NC} integration (kubectl missing)"; record integration SKIP; return
  fi
  # HARD SAFETY GATE: only the sandbox context is ever allowed.
  if [[ "$KUBE_CTX" != "docker-desktop" ]]; then
    echo -e "${YELLOW}[SKIP]${NC} integration: kube context is '${KUBE_CTX:-none}', not 'docker-desktop'."
    echo -e "        Refusing to run against a non-sandbox cluster (prod safety)."
    record integration SKIP; return
  fi
  if ! ls "$TESTS"/integration/*.bats >/dev/null 2>&1; then
    echo -e "${YELLOW}[skip]${NC} no integration tests yet"; record integration SKIP; return
  fi
  if bats "$TESTS"/integration/*.bats; then
    echo -e "${GREEN}[PASS]${NC} integration"; record integration PASS
  else
    echo -e "${RED}[FAIL]${NC} integration"; record integration FAIL
  fi
}

# ---- arg parsing ------------------------------------------------------------
# --with-throwaway is consumed here and exported for the integration tier.
export EDS_WITH_THROWAWAY=false
ARGS=()
for a in "$@"; do
  case "$a" in
    --with-throwaway) export EDS_WITH_THROWAWAY=true ;;
    *) ARGS+=("$a") ;;
  esac
done
TARGET="${ARGS[0]:-default}"

preflight

case "$TARGET" in
  cpp)         run_cpp ;;
  python)      run_python ;;
  shell)       run_shell ;;
  integration) run_integration ;;
  all)         run_cpp; run_python; run_shell; run_integration ;;
  default)     run_cpp; run_python; run_shell
               echo -e "${DIM}(integration not run by default; use './run_tests.sh all' or './run_tests.sh integration')${NC}" ;;
  *)           echo "Unknown target '$TARGET'. Use: cpp | python | shell | integration | all"; exit 2 ;;
esac

# ---- summary ----------------------------------------------------------------
echo
echo -e "${DIM}=== summary ===${NC}"
fail=0
for i in "${!TIER_NAMES[@]}"; do
  n="${TIER_NAMES[$i]}"; r="${TIER_RESULTS[$i]}"
  case "$r" in
    PASS) printf "  ${GREEN}%-12s PASS${NC}\n" "$n" ;;
    FAIL) printf "  ${RED}%-12s FAIL${NC}\n" "$n"; fail=1 ;;
    SKIP) printf "  ${YELLOW}%-12s SKIP${NC}\n" "$n" ;;
  esac
done
[[ $fail -eq 0 ]] && echo -e "${GREEN}OK${NC}" || echo -e "${RED}FAILURES PRESENT${NC}"
exit $fail
