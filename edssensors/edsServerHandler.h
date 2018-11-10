#ifndef EDSSERVERHANDLER
#define EDSSERVERHANDLER
  
#include <netinet/in.h>
#include <stdio.h>
#include <cstring>
#include <netinet/in.h>
#include <sys/socket.h>
#include <netdb.h>
#include <iostream>
#include <vector>
#include <map>
#include <curl/curl.h>

class edsServerHandler
{
  public:
    edsServerHandler(char*& ip);
    void decodeServerData();
    void storeServerData();
    void readSensorConfiguration();
    std::string retreivexml(std::string ipaddr);
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
    std::vector<std::string> sensorConfiguration;
    std::map<std::string,std::vector<std::string>> sc;
};
#endif
