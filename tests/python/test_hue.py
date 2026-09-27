# tests/python/test_hue.py
import os
from conftest import load_module

hue = load_module("huepod_huesensors", "container/huepod/huesensors.py")


def _write_templates(cwd):
    # create_details reads TEMPLATE_FILE (detailes.xml) and
    # TEMPLATE_ITEM_FILE (detail.xml) from os.getcwd() + PATH + filename
    # (string concatenation, not os.path.join). Templates are written at the
    # concatenated location; tests set hue.PATH to os.sep so the path resolves.
    (cwd / hue.TEMPLATE_FILE).write_text(
        "<Devices-Detail-Response></Devices-Detail-Response>"
    )
    (cwd / hue.TEMPLATE_ITEM_FILE).write_text(
        "<sensor type=#DESCRIPTION# date=#DATE# data=#DATA# "
        "batt=#BATT# id=#ID# name=#NAME# />"
    )


def test_create_details_substitutes_temperature(tmp_path, monkeypatch):
    _write_templates(tmp_path)
    monkeypatch.chdir(tmp_path)
    monkeypatch.setattr(hue, "PATH", os.sep)  # getcwd()+os.sep+filename

    payload = {
        "sensors": {
            "1": {
                "type": "ZLLTemperature",
                "state": {"temperature": 2137, "lastupdated": "2026-09-19T10:00:00"},
                "config": {"battery": 88},
                "uniqueid": "00:11:22:33",
                "name": "LivingRoom",
            }
        }
    }

    class FakeResp:
        def raise_for_status(self):
            pass

        def json(self):
            return payload

    monkeypatch.setattr(hue.requests, "get", lambda *a, **k: FakeResp())

    details = hue.create_details()

    assert details is not None
    # temperature 2137 -> "21.37"
    assert "21.37" in details
    assert "88" in details            # battery
    assert "ZLLTemperature" in details
    assert "2026-09-19T10:00:00" in details
    for ph in ("#DESCRIPTION#", "#DATE#", "#DATA#", "#BATT#", "#ID#"):
        assert ph not in details


def test_create_details_returns_none_on_request_error(tmp_path, monkeypatch):
    _write_templates(tmp_path)
    monkeypatch.chdir(tmp_path)
    monkeypatch.setattr(hue, "PATH", os.sep)

    def boom(*a, **k):
        raise hue.requests.RequestException("network down")

    monkeypatch.setattr(hue.requests, "get", boom)
    assert hue.create_details() is None
