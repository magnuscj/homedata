# tests/python/test_wind.py
from conftest import load_module

wind = load_module("windpod_wind", "container/windpod/wind.py")


# --- parse_sensor_data -------------------------------------------------------
def test_parse_sensor_data_speed_and_dir():
    data = [
        {"id": "0x0B", "val": "3.4m/s"},
        {"id": "0x0A", "val": "180"},
    ]
    speed, direction = wind.parse_sensor_data(data)
    assert speed == "3.4"
    assert direction == "180"


def test_parse_sensor_data_strips_units_and_whitespace():
    data = [{"id": "0x0B", "val": "  7.0 m/s "}]
    speed, direction = wind.parse_sensor_data(data)
    assert speed == "7.0"
    assert direction is None


def test_parse_sensor_data_skips_none_val():
    data = [{"id": "0x0B", "val": None}, {"id": "0x0A", "val": "90"}]
    speed, direction = wind.parse_sensor_data(data)
    assert speed is None
    assert direction == "90"


def test_parse_sensor_data_empty():
    speed, direction = wind.parse_sensor_data([])
    assert speed is None and direction is None


# --- update_template_file ----------------------------------------------------
def test_update_template_file_substitutes(tmp_path):
    tmpl = tmp_path / "tmpl_details.xml"
    tmpl.write_text("speed=#SPEED1# dir=#DIR1# a=#ID14# b=#ID15#")
    out = tmp_path / "out.xml"

    wind.update_template_file(str(tmpl), str(out), "5.5", "270")

    result = out.read_text()
    assert "5.5" in result and "270" in result
    assert "1c:69:7a:02:8c:4c:14" in result
    assert "1c:69:7a:02:8c:4c:15" in result
    for ph in ("#SPEED1#", "#DIR1#", "#ID14#", "#ID15#"):
        assert ph not in result


def test_update_template_file_none_defaults_to_zero(tmp_path):
    tmpl = tmp_path / "t.xml"
    tmpl.write_text("#SPEED1#/#DIR1#")
    out = tmp_path / "o.xml"
    wind.update_template_file(str(tmpl), str(out), None, None)
    assert out.read_text() == "0/0"
