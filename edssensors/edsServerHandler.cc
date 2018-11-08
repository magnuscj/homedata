#include "edsServerHandler.h"
#include <netinet/in.h>
#include <stdio.h>
#include <cstring>
#include <netinet/in.h>
#include <sys/socket.h>
#include <netdb.h>
#include <iostream>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <mysql/mysql.h>
#include <iomanip>
#include <time.h>
#include "tinyxml2.h"
#include <vector>
#include <utility>
#include <curl/curl.h>
using namespace std;
using namespace tinyxml2;



static size_t WriteCallback(void *contents, size_t size, size_t nmemb, void *userp)
{
    ((std::string*)userp)->append((char*)contents, size * nmemb);
    return size * nmemb;
}
//Constructor
//-------------------------------

edsServerHandler::edsServerHandler(char*& ip)
{
   ipAddress = ip;
   curl = curl_easy_init();
   sensors.clear();
}


std::string edsServerHandler::retreivexml(std::string ipaddr)
{
  std:string urlstr =  "http://" + ipaddr + "/details.xml";
  
  CURLcode res;
  std::string readBuffer;

  if(curl) 
  {
    curl_easy_setopt(curl, CURLOPT_URL, urlstr.c_str());
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, WriteCallback);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &readBuffer);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, 5L);
    /* example.com is redirected, so we tell libcurl to follow redirection */ 
    curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);
 
    /* Perform the request, res will get the return code */ 
    res = curl_easy_perform(curl);
    /* Check for errors */ 
    if(res != CURLE_OK)
      fprintf(stderr, "curl_easy_perform() failed: %s\n",
              curl_easy_strerror(res));
 
    /* always cleanup */ 
    curl_easy_cleanup(curl);
    return readBuffer;
  }
}

void edsServerHandler::decodeServerData()
{
  string sensorid = "";
  tinyxml2::XMLDocument doc;
  string xmldocstr = this->retreivexml(ipAddress);
  const char* xmldoc = xmldocstr.c_str();
  //std::cout<<"doc"<<this->retreivexml(ipAddress)<<"\n";
  
  XMLError err = doc.Parse(xmldoc);
  std::pair <string,string> sensorType;
  vector <std::pair <string,string>> sensorTypes;

  sensorTypes.push_back(std::make_pair("owd_DS18B20","Temperature"));
  sensorTypes.push_back(std::make_pair("owd_DS18S20","Temperature"));
  sensorTypes.push_back(std::make_pair("owd_DS2423","Counter_A"));

  if(err)
  {
	  printf("Error %d \n", err);
    cout<<"Ip"<<xmldocstr<<"\n";
	  //TODO count error of this type j
    //std::system("clear");
  }
  else
  {
    for( auto sensorType : sensorTypes)
    {
      XMLElement* root    = doc.RootElement();       //Devices-Detail-Response
      XMLNode* rootchild  = root->FirstChild();      //PollCount
      XMLNode *siblingNode= rootchild->NextSibling();//DevicesConnected

      while(rootchild!=NULL)
      { 
        if((sensorType.first.compare(rootchild->Value())==0))
        {
          siblingNode = rootchild->FirstChild();
	        sensorData.type = rootchild->Value();

	        while(siblingNode!=NULL)
          {
            if(!siblingNode->NoChildren() && (strcmp(siblingNode->Value(), "ROMId")==0))
            {
              sensorData.id = siblingNode->FirstChild()->Value();
            }
            
            if(!siblingNode->NoChildren() && (sensorType.second.compare(siblingNode->Value()) ==0))
            {
              sensorData.value = siblingNode->FirstChild()->Value();
            }
            siblingNode=siblingNode->NextSibling();
	        }
	        sensors.push_back(sensorData);
	      }
	      rootchild = rootchild->NextSibling();
      }
    }
  }
  doc.Clear();
}


void edsServerHandler::storeServerData()
{
  MYSQL_RES *result;
  MYSQL_ROW row;
  MYSQL *connection, mysql;
  int state;
  string dbName   = "mydb";
  string tbName   = "table";
  string sensorid = "";
  
  mysql_init(&mysql);

   connection = mysql_real_connect(&mysql,"127.0.0.1","dbuser","dbuser",0,0,0,0);

   if (connection == NULL)
   {
     cout<<mysql_error(&mysql);
     return ;
   }
 
      
  for( auto &sensor : sensors)
  {
    cout<<left;
    cout<<setw(13)<<sensor.type<<sensor.id<<": "<<setw(10)<<sensor.value<<"  "<<ipAddress<<"\n";  
    time_t t = time(NULL);
    struct tm tm = *localtime(&t);

    string date = to_string(tm.tm_year + 1900).append((((1+tm.tm_mon) <=9) ? "0" + to_string(tm.tm_mon+1) : to_string(tm.tm_mon+1 )));
    state = mysql_query(connection, string("CREATE DATABASE "+dbName).c_str());
    state = mysql_query(connection, string("CREATE TABLE "+dbName+"."+ tbName + date + 
		   " (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, sensorid TEXT, data float(23,3), curr_timestamp TIMESTAMP)").c_str());
    string query = "INSERT INTO " + dbName + "." + tbName + date + " (sensorid, data) VALUES('" + sensor.id + "', '" + sensor.value + "')";
    state = mysql_query(connection, query.c_str());
  }
  
  mysql_close(connection);

}


