// tests/cpp/test_stablehash.cc
//
// Unit tests for edsServerHandler::stableHash — the canonical FNV-1a 64-bit
// hash used as the sensorid. This is pure logic: no DB, no network.
//
// The known vector comes from the eds data model: the hash input for a sensor
// is  ROMId + metricType + type. For the details.xml placeholder ROMId "[romId]"
// and owd_DS18B20/Temperature the input is "[romId]Temperatureowd_DS18B20",
// whose FNV-1a-64 value is 18435809319482831335.

#define CATCH_CONFIG_MAIN
#include "catch.hpp"
#include "edsServerHandler.h"

TEST_CASE("stableHash matches the known FNV-1a vector", "[stablehash]") {
    REQUIRE(edsServerHandler::stableHash("[romId]Temperatureowd_DS18B20")
            == "18435809319482831335");
}

TEST_CASE("stableHash of empty string is the FNV offset basis", "[stablehash]") {
    // FNV-1a with no bytes consumed returns the 64-bit offset basis
    // 0xcbf29ce484222325 = 14695981039346656037 (decimal).
    REQUIRE(edsServerHandler::stableHash("") == "14695981039346656037");
}

TEST_CASE("stableHash is deterministic", "[stablehash]") {
    const std::string in = "owd_EDS0068BarometricPressureHg";
    REQUIRE(edsServerHandler::stableHash(in) == edsServerHandler::stableHash(in));
}

TEST_CASE("stableHash distinguishes different inputs", "[stablehash]") {
    REQUIRE(edsServerHandler::stableHash("a") != edsServerHandler::stableHash("b"));
}

TEST_CASE("stableHash single-byte 'a' matches reference FNV-1a-64", "[stablehash]") {
    // Reference: FNV-1a-64("a") = 0xaf63dc4c8601ec8c = 12638187200555641996.
    REQUIRE(edsServerHandler::stableHash("a") == "12638187200555641996");
}
