# tests/python/test_rain.py
import os
import pytest
from conftest import load_module

rain = load_module("rainpod_rain", "container/rainpod/rain.py")


# --- parse_rain_value --------------------------------------------------------
def test_parse_rain_value_found():
    data = [
        {"id": "0x0D", "val": "1.2mm"},
        {"id": "0x11", "val": "5.0mm"},
        {"id": "0x12", "val": "12.3mm"},
    ]
    assert rain.parse_rain_value(data, rain.RAIN_EVENT_KEY) == "1.2"
    assert rain.parse_rain_value(data, rain.RAIN_WEEK_KEY) == "5.0"
    assert rain.parse_rain_value(data, rain.RAIN_MONTH_KEY) == "12.3"


def test_parse_rain_value_strips_and_trims():
    data = [{"id": "0x0D", "val": "  0.75 mm "}]
    assert rain.parse_rain_value(data, "0x0D") == "0.75"


def test_parse_rain_value_missing_key_returns_none():
    data = [{"id": "0x0D", "val": "1.0mm"}]
    assert rain.parse_rain_value(data, "0x11") is None


def test_parse_rain_value_empty_data_returns_none():
    assert rain.parse_rain_value([], "0x0D") is None


# --- update_template_file ----------------------------------------------------
def test_update_template_file_substitutes(tmp_path):
    template = (
        "<rain>#RAIN1# id=#ID16# week=#RAINW1# id=#ID17# "
        "month=#RAINM1# id=#ID18#</rain>"
    )
    tmpl = tmp_path / "tmpl_details.xml"
    tmpl.write_text(template)
    out = tmp_path / "out.xml"

    rain.update_template_file(str(tmpl), str(out), "1.2", "5.0", "12.3")

    result = out.read_text()
    assert "1.2" in result and "5.0" in result and "12.3" in result
    assert rain.MAC_ID16 in result
    assert rain.MAC_ID17 in result
    assert rain.MAC_ID18 in result
    # no unsubstituted placeholders remain
    for ph in ("#RAIN1#", "#RAINW1#", "#RAINM1#", "#ID16#", "#ID17#", "#ID18#"):
        assert ph not in result


def test_update_template_file_none_values_default_to_zero(tmp_path):
    tmpl = tmp_path / "t.xml"
    tmpl.write_text("#RAIN1#/#RAINW1#/#RAINM1#")
    out = tmp_path / "o.xml"
    rain.update_template_file(str(tmpl), str(out), None, None, None)
    assert out.read_text() == "0/0/0"
