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
#include "../../tinyxml2/tinyxml2.h"
#include <vector>
#include <map>
#include <memory>
#include <utility>
#include <curl/curl.h>
#include <fstream>
#include <thread>
#include <algorithm>

using namespace std;
using namespace tinyxml2;

static size_t WriteCallback(void *contents, size_t size, size_t nmemb, void *userp)
{
  ((std::string*)userp)->append((char*)contents, size * nmemb);
  return size * nmemb;
}

edsServerHandler::edsServerHandler(std::string ip)
{
  startTime = std::make_shared<std::chrono::system_clock::time_point>
              (std::chrono::system_clock::now());

  ipAddress = ip;
  curl = curl_easy_init();
  sensors.clear();
  std::ifstream infile("edsServerHandlerConf.txt");
  std::string line;
  std::string item, value;

  while (std::getline(infile, line))
  {
    std::istringstream iss(line);
    if (!(iss >> item >> value))
    { break; } // error
    else
    {
      if(item=="dbip")
      {
         dbIpAddress = new char[value.length() + 1];
         strcpy( dbIpAddress, value.c_str());
      }
      else
      {
        sensorTypes.push_back(std::make_pair(item,value));
      }
    }
  }
  this->connectToDatabase();
}

edsServerHandler::~edsServerHandler()
{
  delete(dbIpAddress);
  mysql_close(dbConnection);
  mysql_thread_end();
}

std::shared_ptr<std::string> edsServerHandler::retreivexml(std::string ipaddr)
{
  std::string urlstr =  "http://" + ipaddr + "/details.xml";

  CURLcode res;
  std::string readBuffer = "";

  if(curl)
  {
    curl_easy_setopt(curl, CURLOPT_URL, urlstr.c_str());
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, WriteCallback);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &readBuffer);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, 5L);
    curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);

    /* Perform the request, res will get the return code */
    res = curl_easy_perform(curl);
    /* Check for errors */
    if(res != CURLE_OK)
    {
       fprintf(stderr, "curl_easy_perform() failed: %s\n",
       curl_easy_strerror(res));
    }
    curl_easy_cleanup(curl);
  }
  return std::make_shared<std::string> (readBuffer);
}

void edsServerHandler::decodeServerData()
{
  string sensorid = "";
  tinyxml2::XMLDocument doc;
  std::shared_ptr<string> xmldocstr = this->retreivexml(ipAddress);
  const char* xmldoc = xmldocstr->c_str();
  XMLError err = doc.Parse(xmldoc);

  if(err)
  {
	  //printf("Error %d \n", err);
	  return;
  }
  else
  {
    XMLElement* root    = doc.RootElement();       //Devices-Detail-Response
    XMLNode* rootchild  = root->FirstChild();      //PollCount
    XMLNode* firstChild = root->FirstChild();      //PollCount
    XMLNode *siblingNode= rootchild->NextSibling();//DevicesConnected
    for(auto a : sensorTypes)
    {
      while(rootchild != NULL)
      {
        bool supported = false;
        string metricType = "";

        if(a.first.compare(rootchild->Value()) == 0)
        {
          supported = true;
          metricType = a.second;
        }
        
        if(supported)
        {
          std::shared_ptr<sensor> sens = std::make_shared<sensor>();
          siblingNode = rootchild->FirstChild();
          sens->type = rootchild->Value();

          while(siblingNode != NULL)
          {
            if(!siblingNode->NoChildren() && (strcmp(siblingNode->Value(), "ROMId") == 0))
            {
              sens->id = siblingNode->FirstChild()->Value();
            }

            if(!siblingNode->NoChildren()&&(strcmp(siblingNode->Value(),metricType.c_str()) == 0))
            {
              sens->value = siblingNode->FirstChild()->Value();
            }
            siblingNode=siblingNode->NextSibling();
          }
          
          sens->id = std::to_string(std::hash<std::string>{}(sens->id + metricType + sens->type));
          sens->unit = metricType;

          if(!sensorConfigurations[sens->id])
          {
            this->writeSensorConfiguration(sens->id);
          }
          sensors.push_back(std::move(sens));
        }
        rootchild = rootchild->NextSibling();
      }
      rootchild = firstChild;
    }
  }
  doc.Clear();
  std::sort(sensors.begin(), sensors.end(), [](std::shared_ptr<sensor>a, std::shared_ptr<sensor> b) {return a->id > b->id;});
  
}

void edsServerHandler::storeServerData()
{
  MYSQL_RES *result;
  MYSQL_ROW row;
  int state;
  string dbName   = "mydb";
  string tbName   = "table";
  string sensorid = "";

  if(sensors.size() > 0)
    for( auto &sensor : sensors)
	 {
		time_t t = time(NULL);
		struct tm tm = *localtime(&t);
		string date = to_string(tm.tm_year + 1900).append((((1+tm.tm_mon) <=9) ? "0" +
							 to_string(tm.tm_mon+1) : to_string(tm.tm_mon+1 )));
		state = mysql_query(dbConnection, string("CREATE DATABASE "+dbName).c_str());
		state = mysql_query(dbConnection, string("CREATE TABLE "+dbName+"."+ tbName + date +
			  " (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, sensorid TEXT, data float(23,3),\
			curr_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)").c_str());
			string query = "INSERT INTO " + dbName + "." + tbName + date + " (sensorid, data) VALUES('"
							  + sensor->id + "', '" + sensor->value + "')";
		state = mysql_query(dbConnection, query.c_str());
	 }
  
  stopTime = std::make_shared<std::chrono::system_clock::time_point>
               (std::chrono::system_clock::now());
}

void edsServerHandler::printServerData()
{
  if(sensors.size() > 0)
    for( auto &sensor : sensors)
	 {
		  cout<<sensor->id<<" "<<sensor->value<<" "<<sensor->unit<<endl;		
	 }
}

void edsServerHandler::printIdValue(std::string id)
{
  if(sensors.size() > 0)
    for( auto &sensor : sensors)
	 {
     if(!id.compare(sensor->id))
		  cout<<sensor->value<<endl;		
	 }
}

std::ostream& operator<< (std::ostream& stream, edsServerHandler& eds)
{
  eds.print();
  return stream;
}

void const edsServerHandler::print()
{
  std::chrono::duration<double> elapsed_seconds = *stopTime-*startTime;
  cout<<left;
  std::thread::id this_id = std::this_thread::get_id();
  std::cout<<"\033[1;32m"<<setw(14)<<ipAddress<<"\033[0m"<<" ("<<elapsed_seconds.count()
           <<"s) thread id: "<<this_id<<"\n";

  for( auto &sensor : sensors)
  {
    cout<<left;
    if(sensorConfigurations[sensor->id])
       cout<<setw(0)<<""<<setw(15)<<sensor->type<<setw(22)<<sensor->id<<setw(7)
         <<sensorConfigurations[sensor->id]->at(1)<<": "<<setw(10)<<sensor->value
         <<"("<<sensor->unit<<")"<<"\n";
    else
       cout<<setw(0)<<""<<setw(15)<<sensor->type<<setw(22)<<sensor->id<<setw(7)
         <<"---"<<": "<<setw(10)<<sensor->value<<"("<<sensor->unit<<")"<<"\n";
  }
  cout<<endl;
}

void edsServerHandler::readSensorConfiguration()
{
  MYSQL_RES *result;
  MYSQL_ROW row;

  const char* dbName = "mydb";
  const char* tbName = "sensorconfig";

  std::string db(dbName);
  std::string tb(tbName);

  string query = "SELECT * FROM " + db + "." + tb;
  int num_fields =0;
  int num_rows = 0;

  if (mysql_query(dbConnection, query.c_str()))
  {
    // error
  }
  else // query succeeded, process any data returned by it
  {
    result = mysql_store_result(dbConnection);
    if (result)  // there are rows
    {
      while (row = mysql_fetch_row(result))
      {
        std::shared_ptr<std::vector<string>> sensConf = std::make_shared<std::vector<string>>();

        for(int i = 1; i < mysql_num_fields(result)-1; i++)
        {
          sensConf->emplace_back(row[i]);
        }
        sensorConfigurations.emplace(row[1], std::move(sensConf));
      }
    }
    else  // mysql_store_result() returned nothing; should it have?
    {
      if(mysql_field_count(dbConnection) == 0)
      {
          // query does not return data, (it was not a SELECT)
          num_rows = mysql_affected_rows(dbConnection);
      }
      else // mysql_store_result() should have returned data
      {
          fprintf(stderr, "Error: %s\n", mysql_error(dbConnection));
      }
    }
    mysql_free_result(result);
  }
}

void edsServerHandler::writeSensorConfiguration(std::string sensorid)
{
  MYSQL_RES *result;
  MYSQL_ROW row;
  int state;
  string dbName   = "mydb";
  string tbName   = "sensorconfig";

  state = mysql_query(dbConnection, string("CREATE TABLE "+ dbName+"." + tbName +
          " (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, sensorid TEXT NOT NULL,\
          sensorname TEXT NOT NULL, color TEXT NOT NULL, visible TEXT NOT NULL,\
          type TEXT NOT NULL)").c_str());

  string query = "INSERT INTO " + dbName + "." + tbName +  " (sensorid,sensorname,\
                 color,visible, type) VALUES('" + sensorid + "','name','black',\
                 'false', 'default'" + ")";
  state = mysql_query(dbConnection, query.c_str());
}

void edsServerHandler::connectToDatabase()
{
  const char* dbuser = "dbuser";
  const char* dbpwd  = "kmjmkm54C#";

  if(dbConnection == NULL)
  {
     for(int i = 0;i<10;i++)
     {
       dbConnection = mysql_init(dbConnection);
       if(dbConnection != NULL)
       {
         i=10;
       }
       else
         sleep(1);
     }
     
     for(int i = 0;i<10;i++)
     {
        dbConnection = mysql_real_connect(dbConnection,dbIpAddress,dbuser,dbpwd,0,3306,0,0);
        if(dbConnection != NULL)
        {
          i=10;
        }
        else
         sleep(1);
     }
   }

  if(dbConnection == NULL)
  {
    cout<<"Mysql error"<<endl;
    cout<<"Error:    "<<mysql_error(dbConnection)<<endl; //TODO Why no error on fault?
    cout<<"Error no: "<<mysql_errno(dbConnection)<<endl; //TODO Why no error on fault?
    exit(1);
  }
}
