#include "edsServerHandler.h"
#include <fstream>
#include <sstream>
#include <iostream>
#include <cassert>

static std::string readFile(const std::string& path)
{
  std::ifstream f(path);
  std::ostringstream ss;
  ss << f.rdbuf();
  return ss.str();
}

// Read sensorTypes from conf (same logic as main constructor, skipping db/smtp keys)
static std::vector<std::pair<std::string,std::string>> readSensorTypes(const std::string& confPath)
{
  std::vector<std::pair<std::string,std::string>> types;
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

int main()
{
  edsServerHandler eds(readSensorTypes("edsServerHandlerConf.txt"));
  eds.decodeXml(readFile("details.xml"));

  const auto& sensors = eds.getSensors();

  // 8 sensors expected from conf: DS18B20/Temp, DS18S20/Temp, DS2423/Counter_A,
  // DS2438/Temp, EDS0068/BarometricPressureHg, EDS0068/Humidity,
  // EDS0065/Humidity, EDS0065/Temperature
  assert(sensors.size() == 8 && "Expected 8 sensors");

  struct Expected { std::string type; std::string unit; std::string value; };
  Expected expected[] = {
    {"owd_DS18B20",  "Temperature",          "27.0000"},
    {"owd_DS18S20",  "Temperature",          "29.5000"},
    {"owd_DS2423",   "Counter_A",            "6629115"},
    {"owd_DS2438",   "Temperature",          "32.375"},
    {"owd_EDS0068",  "BarometricPressureHg", "30.204"},
    {"owd_EDS0068",  "Humidity",             "70.5000"},
    {"owd_EDS0065",  "Humidity",             "31.2500"},
    {"owd_EDS0065",  "Temperature",          "24.5625"},
  };

  for (const auto& e : expected) {
    bool found = false;
    for (const auto& s : sensors) {
      if (s->type == e.type && s->unit == e.unit && s->value == e.value) {
        found = true;
        break;
      }
    }
    if (!found) {
      std::cerr << "FAIL: missing sensor type=" << e.type
                << " unit=" << e.unit << " value=" << e.value << "\n";
      return 1;
    }
  }

  std::cout << "PASS: all " << sensors.size() << " sensors verified\n";
  return 0;
}
