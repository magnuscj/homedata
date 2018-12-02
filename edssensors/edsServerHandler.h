#ifndef EDSSERVERHANDLER
#define EDSSERVERHANDLER
  
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

    std::vector<std::shared_ptr<sensor>> senss;

    std::map<std::string,std::shared_ptr<std::vector<std::string>>> sensorConfigurations;

    std::chrono::system_clock::time_point startTime;
    std::chrono::system_clock::time_point stopTime;
    
};
#endif
