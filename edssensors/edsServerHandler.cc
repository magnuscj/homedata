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

using namespace std;
using namespace tinyxml2;



static size_t WriteCallback(void *contents, size_t size, size_t nmemb, void *userp)
{
    ((std::string*)userp)->append((char*)contents, size * nmemb);
    return size * nmemb;
}

edsServerHandler::edsServerHandler(char*& ip)
{
  startTime = std::make_shared<std::chrono::system_clock::time_point> (std::chrono::system_clock::now());

  ipAddress = ip;
  curl = curl_easy_init();
  senss.clear();
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
    }
  }

  //TODO check the if db connections are best done in constructor/destructor
}

edsServerHandler::~edsServerHandler()
{
  delete(dbIpAddress);
}

std::shared_ptr<std::string> edsServerHandler::retreivexml(std::string ipaddr)
{
  std::string urlstr =  "http://" + ipaddr + "/details.xml";
  
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
    return std::make_shared<std::string> (readBuffer); 
  }
}

void edsServerHandler::decodeServerData()
{
  string sensorid = "";
  tinyxml2::XMLDocument doc;
  std::shared_ptr<string> xmldocstr = this->retreivexml(ipAddress);
  const char* xmldoc = xmldocstr->c_str();
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
          std::shared_ptr<sensor> sens = std::make_shared<sensor>();
          siblingNode = rootchild->FirstChild();
          sens->type = rootchild->Value();

	  while(siblingNode!=NULL)
          {
            if(!siblingNode->NoChildren() && (strcmp(siblingNode->Value(), "ROMId")==0))
            {
              sens->id = siblingNode->FirstChild()->Value();
            }
            
            if(!siblingNode->NoChildren() && (sensorType.second.compare(siblingNode->Value()) ==0))
            {
              sens->value = siblingNode->FirstChild()->Value();
            }
            siblingNode=siblingNode->NextSibling();
	  }
          if(!sensorConfigurations[sens->id])
          {
            this->writeSensorConfiguration(sens->id);
          }
          senss.push_back(std::move(sens));
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
  int state;
  string dbName   = "mydb";
  string tbName   = "table";
  //const char* dbAddr = "127.0.0.1";
  const char* dbuser = "dbuser";
  const char* dbpwd  = "dbuser";
  string sensorid = "";
  
  MYSQL* mysql = mysql_init(NULL);
  //cout<<mysql_error(mysql);

  if(mysql == NULL)
  {
     cout<<"mysql is NULL in storeServerData"<<endl;
     for(int i = 0;i<10;i++)
     {
<<<<<<< HEAD
       cout<<"Again!\n";
       sleep(5);
=======
       //cout<<"Again!\n";
       sleep(1);
>>>>>>> 0142123df01d7b279fb546c82f970cfe5eff51cb
       mysql = mysql_init(NULL);
       if(mysql != NULL)
       {
         i=10;
       }
     }
     cout<<"Error"<<endl;
     return;
  }  		

  mysql = mysql_real_connect(mysql,dbIpAddress,dbuser,dbpwd,0,0,0,0);
  cout<<mysql_error(mysql);
  
  if (mysql == NULL)
  {
    //cout<<dbIpAddress<<std::endl;
    //cout<<mysql_error(mysql);
    return ;
  }
 
  for( auto &sensor : senss)
  {
    
    time_t t = time(NULL);
    struct tm tm = *localtime(&t);

    string date = to_string(tm.tm_year + 1900).append((((1+tm.tm_mon) <=9) ? "0" + to_string(tm.tm_mon+1) : to_string(tm.tm_mon+1 )));
    state = mysql_query(mysql, string("CREATE DATABASE "+dbName).c_str());
    state = mysql_query(mysql, string("CREATE TABLE "+dbName+"."+ tbName + date + 
		   " (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, sensorid TEXT, data float(23,3), curr_timestamp TIMESTAMP)").c_str());
    string query = "INSERT INTO " + dbName + "." + tbName + date + " (sensorid, data) VALUES('" + sensor->id + "', '" + sensor->value + "')";
    state = mysql_query(mysql, query.c_str());
  }

  mysql_close(mysql);
  mysql_thread_end();
  stopTime = std::make_shared<std::chrono::system_clock::time_point> (std::chrono::system_clock::now());// std::chrono::system_clock::now();
}

std::ostream& operator<< (std::ostream& stream, edsServerHandler& eds)
{
  eds.print();  
}

void const edsServerHandler::print()
{
  std::chrono::duration<double> elapsed_seconds = *stopTime-*startTime;
  cout<<left;
  std::thread::id this_id = std::this_thread::get_id();
  std::cout<<"\033[1;32m"<<setw(14)<<ipAddress<<"\033[0m"<<" ("<<elapsed_seconds.count()<<"s) thread id: "<<this_id<<"\n";
  
  //sensorConfigurations  
  for( auto &sensor : senss)
  {    
    cout<<left;
    if(sensorConfigurations[sensor->id])
       cout<<setw(0)<<""<<setw(15)<<sensor->type<<setw(17)<<sensor->id<<setw(7)<<sensorConfigurations[sensor->id]->at(1)<<": "<<setw(10)<<sensor->value<<"\n";
    else
       cout<<setw(0)<<""<<setw(15)<<sensor->type<<setw(17)<<sensor->id<<setw(7)<<"---"<<": "<<setw(10)<<sensor->value<<"\n";
  }
  cout<<endl;  
}


void edsServerHandler::readSensorConfiguration()
{
  MYSQL_RES *result;
  MYSQL_ROW row;
  
  const char* dbName = "mydb";
  const char* tbName = "sensorconfig";
  const char* dbAddr = "127.0.0.1";
  const char* dbuser = "dbuser";
  const char* dbpwd  = "dbuser";
  
  if(mysql_thread_safe()== 0)
	  cout<<"Not safe\n";

  MYSQL* mysql = mysql_init(NULL);
  //cout<<mysql_error(mysql);

  if(mysql == NULL)
  {
     cout<<"mysql is NULL "<<ipAddress<<"\n";
     for(int i = 0;i<10;i++)
     {
       cout<<"try again!\n";
       cout<<mysql_error(mysql);
       sleep(5);
       mysql = mysql_init(NULL);

       cout<<"tried again! "<<mysql_get_server_info(mysql)<<"\n";
       if(mysql != NULL)
       {
	 cout<<"success!\n";
         i=10;
       }
     }
     cout<<"Give up\n";
     return;
   }  		


  mysql = mysql_real_connect(mysql,dbAddr,dbuser,dbpwd,dbName,0,NULL,0);
  cout<<mysql_error(mysql);

  if (mysql == NULL)
  {
    cout<<mysql_error(mysql);
    return ;
  }

  std::string db(dbName);
  std::string tb(tbName);

  string query = "SELECT * FROM " + db + "." + tb;
  int num_fields =0;
  int num_rows = 0;

  if (mysql_query(mysql, query.c_str()))
  {
    // error
  }
  else // query succeeded, process any data returned by it
  {
    result = mysql_store_result(mysql);
    if (result)  // there are rows
    {
      while ((row = mysql_fetch_row(result)))
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
      if(mysql_field_count(mysql) == 0)
      {
          // query does not return data, (it was not a SELECT)
          num_rows = mysql_affected_rows(mysql);
      }
      else // mysql_store_result() should have returned data
      {
          fprintf(stderr, "Error: %s\n", mysql_error(mysql));
      }
    }
    mysql_free_result(result);
  }
  mysql_close(mysql);
  mysql_thread_end();
}

void edsServerHandler::writeSensorConfiguration(std::string sensorid)
{
  MYSQL_RES *result;
  MYSQL_ROW row;
  int state;
  string dbName   = "mydb";
  string tbName   = "sensorconfig";
  const char* dbAddr = "127.0.0.1";
  const char* dbuser = "dbuser";
  const char* dbpwd  = "dbuser";

  MYSQL* mysql = mysql_init(NULL);

  mysql = mysql_real_connect(mysql,dbAddr,dbuser,dbpwd,0,0,0,0);

  if (mysql == NULL)
  {
    std::cout<<dbAddr<<std::endl;
    cout<<mysql_error(mysql);
    return ;
  }
  state = mysql_query(mysql, string("CREATE TABLE "+ dbName+"." + tbName + " (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, sensorid TEXT NOT NULL, sensorname TEXT NOT NULL, color TEXT NOT NULL, visible TEXT NOT NULL, type TEXT NOT NULL)").c_str());

  string query = "INSERT INTO " + dbName + "." + tbName +  " (sensorid, sensorname, color,visible, type) VALUES('" + sensorid + "', 'name','black', 'false', 'default'" + ")";
  state = mysql_query(mysql, query.c_str());
  mysql_close(mysql);
  mysql_thread_end();

}













