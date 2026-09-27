# tests/python/conftest.py
#
# Helpers to load the sensor-stack Python modules by file path. Each pod
# collector guards its loop with `if __name__ == "__main__":`, so importing the
# module under a different name loads the functions WITHOUT starting the poll
# loop. Some modules import third-party libs (e.g. `requests`); when a lib is
# absent we install a lightweight stub into sys.modules before loading so the
# pure transform functions remain testable.

import importlib.util
import os
import sys
import types

REPO_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))


def _ensure_requests_stub():
    """Provide a minimal `requests` module if the real one isn't installed.
    Tests that need HTTP behaviour monkeypatch requests.get themselves."""
    if "requests" in sys.modules:
        return
    try:
        import requests  # noqa: F401
        return
    except ImportError:
        pass
    stub = types.ModuleType("requests")

    class RequestException(Exception):
        pass

    def _get(*_a, **_k):  # replaced via monkeypatch in tests that need it
        raise RequestException("requests.get not stubbed in this test")

    stub.get = _get
    stub.RequestException = RequestException
    sys.modules["requests"] = stub


def load_module(name, relpath):
    """Load a module from a repo-relative path under a unique name.

    `relpath` is relative to the repo root, e.g. 'container/rainpod/rain.py'.
    The module's directory is temporarily added to sys.path so any sibling
    imports resolve, and __name__ is NOT '__main__', so main() does not run.
    """
    _ensure_requests_stub()
    abspath = os.path.join(REPO_ROOT, relpath)
    moddir = os.path.dirname(abspath)
    added = False
    if moddir not in sys.path:
        sys.path.insert(0, moddir)
        added = True
    try:
        spec = importlib.util.spec_from_file_location(name, abspath)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        return module
    finally:
        if added:
            sys.path.remove(moddir)
