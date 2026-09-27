// tests/cpp/test_decode_xml.cc
//
// Unit tests for edsServerHandler::decodeXml — parses a sensor details.xml into
// the sensor list and computes each sensorid as FNV-1a(ROMId + metricType + type).
// Pure logic: uses the types-only constructor so no DB connection is opened.
//
// Ported from edssensors/test_decodeXml.cc (which this supersedes). Fixtures live
// in tests/cpp/fixtures/ and are located relative to this source file so the test
// runs regardless of the current working directory.

#define CATCH_CONFIG_MAIN
#include "catch.hpp"
#include "edsServerHandler.h"

#include <fstream>
#include <sstream>
#include <string>
#include <vector>
#include <utility>

#ifndef FIXTURE_DIR
#define FIXTURE_DIR "fixtures"
#endif

namespace {

std::string readFile(const std::string& path) {
    std::ifstream f(path);
    std::ostringstream ss;
    ss << f.rdbuf();
    return ss.str();
}

// Mirror the sensorTypes parsing from the eds constructor: skip db/smtp keys.
std::vector<std::pair<std::string, std::string>> readSensorTypes(const std::string& confPath) {
    std::vector<std::pair<std::string, std::string>> types;
    std::ifstream f(confPath);
    std::string line, item, value;
    while (std::getline(f, line)) {
        std::istringstream iss(line);
        if (!(iss >> item >> value)) break;
        if (item == "dbip" || item == "dbuser" || item == "dbpwd" ||
            item == "smtp_user" || item == "smtp_pwd" ||
            item == "smtp_from" || item == "smtp_to") continue;
        types.push_back({item, value});
    }
    return types;
}

std::string fixture(const std::string& name) {
    return std::string(FIXTURE_DIR) + "/" + name;
}

struct Expected { std::string type; std::string unit; std::string value; };

} // namespace

TEST_CASE("decodeXml parses all expected sensors from details.xml", "[decodexml]") {
    edsServerHandler eds(readSensorTypes(fixture("edsServerHandlerConf.txt")));
    eds.decodeXml(readFile(fixture("details.xml")));
    const auto& sensors = eds.getSensors();

    REQUIRE(sensors.size() == 8);

    const Expected expected[] = {
        {"owd_DS18B20", "Temperature",          "27.0000"},
        {"owd_DS18S20", "Temperature",          "29.5000"},
        {"owd_DS2423",  "Counter_A",            "6629115"},
        {"owd_DS2438",  "Temperature",          "32.375"},
        {"owd_EDS0068", "BarometricPressureHg", "30.204"},
        {"owd_EDS0068", "Humidity",             "70.5000"},
        {"owd_EDS0065", "Humidity",             "31.2500"},
        {"owd_EDS0065", "Temperature",          "24.5625"},
    };

    for (const auto& e : expected) {
        bool found = false;
        for (const auto& s : sensors) {
            if (s->type == e.type && s->unit == e.unit && s->value == e.value) {
                found = true;
                break;
            }
        }
        INFO("missing sensor type=" << e.type << " unit=" << e.unit << " value=" << e.value);
        REQUIRE(found);
    }
}

TEST_CASE("decodeXml assigns the canonical FNV-1a sensorid", "[decodexml]") {
    edsServerHandler eds(readSensorTypes(fixture("edsServerHandlerConf.txt")));
    eds.decodeXml(readFile(fixture("details.xml")));
    const auto& sensors = eds.getSensors();

    // details.xml uses placeholder ROMId "[romId]"; for owd_DS18B20/Temperature
    // the hash input is "[romId]Temperatureowd_DS18B20".
    const std::string expectedId = "18435809319482831335";
    const std::string legacyId =
        std::to_string(std::hash<std::string>{}("[romId]Temperatureowd_DS18B20"));

    bool checked = false;
    for (const auto& s : sensors) {
        if (s->type == "owd_DS18B20" && s->unit == "Temperature") {
            INFO("sensorid was '" << s->id << "'"
                 << (s->id == legacyId ? " (this is the LEGACY std::hash!)" : ""));
            REQUIRE(s->id == expectedId);
            checked = true;
            break;
        }
    }
    REQUIRE(checked);
}

TEST_CASE("decodeXml on malformed XML yields no sensors", "[decodexml]") {
    edsServerHandler eds(readSensorTypes(fixture("edsServerHandlerConf.txt")));
    eds.decodeXml("this is not xml <<< >>>");
    REQUIRE(eds.getSensors().empty());
}
