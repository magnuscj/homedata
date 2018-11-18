#ifndef EDSSERVERHANDLER
#define EDSSERVERHANDLER
  
#include <netinet/in.h>
#include <stdio.h>
#include <cstring>
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

class edsServerHandler
{
  public:
    edsServerHandler(char*& ip);
    void decodeServerData();
    void storeServerData();
    void readSensorConfiguration();
    void writeSensorConfiguration();
    void const print();
    std::string retreivexml(std::string ipaddr);
    friend std::ostream& operator<< (std::ostream& stream, edsServerHandler& eds);

  private:
    char* ipAddress;
    CURL *curl;
    struct sensor
    {
      std::string type;
      std::string id;
      std::string value;
    } sensorData;

    std::vector<sensor> sensors;
    std::vector<std::shared_ptr<sensor>> senss;
    std::vector<std::string> sensorConfiguration;
    std::map<std::string,std::vector<std::string>> sc;
    std::chrono::system_clock::time_point startTime;
    std::chrono::system_clock::time_point stopTime;
    
};
#endif
