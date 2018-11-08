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
#include <curl/curl.h>

class edsServerHandler
{
  public:
    edsServerHandler(char*& ip);
    void decodeServerData();
    void storeServerData();
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
};
#endif
