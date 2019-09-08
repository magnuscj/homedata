#ifndef EDSSERVERHANDLER_H_
#define EDSSERVERHANDLER_H_

#include <netinet/in.h>
#include <stdio.h>
#include <cstring>
#include <string.h>
#include <netinet/in.h>
#include <sys/socket.h>
#include <netdb.h>
#include <iostream>
#include <ostream>
#include <vector>
#include <map>
#include <memory>
#include <curl/curl.h>
#include <chrono>
#include <mysql/mysql.h>
class edsServerHandler
{
  public:
    edsServerHandler(char*& ip);
    ~edsServerHandler();
    void decodeServerData();
    void storeServerData();
    void readSensorConfiguration();
    void writeSensorConfiguration(std::string sensor);
    void const print();
    void connectToDatabase();
    std::shared_ptr<std::string> retreivexml(std::string ipaddr);
    friend std::ostream& operator<< (std::ostream& stream, edsServerHandler& eds);

  private:
    char* ipAddress;
    char* dbIpAddress;
    CURL *curl;
    MYSQL* dbConnection;
    struct sensor
    {
      std::string type;
      std::string id;
      std::string value;
      std::string unit;
    } sensorData;

    std::vector<std::shared_ptr<sensor>> sensors;
    std::vector <std::pair <std::string, std::string>> sensorTypes;
    std::map<std::string,std::shared_ptr<std::vector<std::string>>> sensorConfigurations;
    std::shared_ptr<std::chrono::system_clock::time_point> startTime;
    std::shared_ptr<std::chrono::system_clock::time_point> stopTime;
};
#endif  // EDSSERVERHANDLER_H_
