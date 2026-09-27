# tests/python/test_humi.py
from conftest import load_module

humi = load_module("humipod_humi", "container/humipod/humi.py")


def test_update_template_file_ch_soil_substitution(tmp_path):
    # detail item template with the placeholders humi.py substitutes
    item = tmp_path / "detail_t.xml"
    item.write_text(
        "<sensor><data>#DATA#</data><name>#NAME#</name>"
        "<batt>#BATT#</batt><id>#ID#</id><date>#DATE#</date></sensor>"
    )
    # container template must contain the closing tag humi.py splices into
    container = tmp_path / "detailes_t.xml"
    container.write_text("<Devices-Detail-Response></Devices-Detail-Response>")
    out = tmp_path / "out.xml"

    data = {
        "ch_soil": [
            {"humidity": "42%", "name": "Palett", "battery": "3", "channel": "05"},
            {"humidity": "13%", "name": "Basil",  "battery": "4", "channel": "06"},
        ]
    }

    humi.update_template_file(str(item), str(container), str(out), data)

    result = out.read_text()
    # humidity had its % stripped
    assert "42" in result and "13" in result
    assert "%" not in result.split("</name>")[0]  # no stray % in the data field
    assert "Palett" in result and "Basil" in result
    # channel appended to MAC prefix for the ID
    assert humi.MAC_ADDRESS + "05" in result
    assert humi.MAC_ADDRESS + "06" in result
    # placeholders fully consumed
    for ph in ("#DATA#", "#NAME#", "#BATT#", "#ID#", "#DATE#"):
        assert ph not in result


def test_update_template_file_ignores_non_ch_soil_keys(tmp_path):
    item = tmp_path / "detail_t.xml"
    item.write_text("<s>#DATA#</s>")
    container = tmp_path / "detailes_t.xml"
    container.write_text("<Devices-Detail-Response></Devices-Detail-Response>")
    out = tmp_path / "out.xml"

    # no ch_soil -> nothing spliced, container passes through unchanged
    humi.update_template_file(str(item), str(container), str(out), {"other": []})

    assert out.read_text() == "<Devices-Detail-Response></Devices-Detail-Response>"
