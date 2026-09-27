# tests/python/test_eds_web.py
from conftest import load_module

eds_web = load_module("edssensors_eds_web", "edssensors/eds_web.py")


def test_to_html_host_header_row():
    line = "192.168.50.230 (0.5s) thread id: 140234"
    html = eds_web.to_html(line)
    assert 'class="host"' in html
    assert "192.168.50.230" in html
    assert "0.5s" in html
    assert "140234" in html


def test_to_html_sensor_data_row():
    # Format: <type> <numeric id> <name> : <value> (<unit>)
    line = "owd_DS18B20 18435809319482831335 Ute : 27.0000 (Temperature)"
    html = eds_web.to_html(line)
    assert "<td>owd_DS18B20</td>" in html
    assert "<td>18435809319482831335</td>" in html
    assert "<td>Ute</td>" in html
    assert "<td>27.0000</td>" in html
    assert "<td>Temperature</td>" in html
    assert 'class="host"' not in html


def test_to_html_misc_line_falls_through():
    line = "Elapsed time (system): 0.42s"
    html = eds_web.to_html(line)
    assert 'class="misc"' in html
    assert "Elapsed time" in html


def test_to_html_blank_lines_skipped():
    assert eds_web.to_html("\n\n   \n") == ""


def test_to_html_malformed_input_does_not_crash():
    # weird bytes / partial rows should fall through to misc, not raise
    html = eds_web.to_html("owd_X (unterminated\n:::\n<garbage>")
    assert isinstance(html, str)
    assert 'class="misc"' in html


def test_frame_separator_split_selects_penultimate_frame():
    # eds_web splits the buffer on FRAME_SEP and renders parts[-2]
    frame_a = "192.168.50.230 (0.1s) thread id: 1"
    frame_b = "192.168.50.230 (0.2s) thread id: 2"
    buf = eds_web.FRAME_SEP + frame_a + eds_web.FRAME_SEP + frame_b + eds_web.FRAME_SEP
    parts = buf.split(eds_web.FRAME_SEP)
    assert len(parts) >= 2
    penultimate = parts[-2]
    assert "0.2s" in penultimate  # the last complete frame before the trailing sep


def test_ansi_stripping_removes_escape_codes():
    # ANSI regex should strip color codes eds emits around fields
    dirty = "\x1b[1;32m192.168.50.230\x1b[0m (0.1s) thread id: 7"
    clean = eds_web.ANSI.sub("", dirty)
    assert "\x1b" not in clean
    html = eds_web.to_html(clean)
    assert "192.168.50.230" in html
