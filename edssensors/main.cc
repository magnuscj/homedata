#include <iostream>
#include <string.h>
#include <time.h>
#include "edsServerHandler.h"
#include "communication.h"
#include <thread>
#include <chrono>
#include <memory>
#include <algorithm>
#include <vector>
#include <unistd.h>
#include <iomanip>
#include <sstream>
#include <fstream>
#include <cmath>
#include <arpa/inet.h>
using namespace std;

void edsHandler(std::string ipadr )
{
  std::shared_ptr<edsServerHandler> eds = std::make_shared<edsServerHandler>(ipadr);
  eds->readSensorConfiguration();
  eds->decodeServerData();
  eds->storeServerData();
  cout<<*eds;
}

//-----------------------------------------------------
// Extract the memory, in Kb, from the provided string
//-----------------------------------------------------
int extractMemory(char* line)
{
  int i = strlen(line);
  const char* p = line;
  while (*p <'0' || *p > '9') p++;
  line[i-3] = '\0';
  i = atoi(p);
  return i;
}

//-----------------------------------------------------
// Returns the memory, in Kb, consumed by the program
//-----------------------------------------------------
int getMemory()
{
  FILE* file = fopen("/proc/self/status", "r");
  int result = -1;
  char line[128];

  while (fgets(line, 128, file) != NULL)
  {
    if (strncmp(line, "VmRSS:", 6) == 0)
    {
      result = extractMemory(line);
      break;
    }
  }
  fclose(file);
  return result;
}

int main(int argc, char* argv[])
{
  std::vector<std::thread> edsServers;
  std::vector<double> elapsedTime;
  std::vector<std::string> ips;
  int mem = 0;
  int noOfBins = 7*10;
  std::vector<int> bins(noOfBins,0);
  communication com;
  struct sockaddr_in sa;
  bool exit = false;
  bool pServerData = false;
  bool pIdvalue = false;

  for(int i = 1;i < argc;i++)
  {
    std::string arg = argv[i];
    if(inet_pton(AF_INET, argv[i], &(sa.sin_addr)) == 1)
    {
      ips.emplace_back(argv[i]);
    }

    if(!arg.compare("-e"))
    {
      exit = true;
    }

    if(!arg.compare("-s"))
    {
      if(argc != 3)
       return 0;

      if(!(inet_pton(AF_INET, argv[2], &(sa.sin_addr)) == 1))
        return 0;

      pServerData = true;
    }

    if(!arg.compare("-i"))
    {
      pIdvalue = true;
    }
  }

  if(pServerData)
  {
    std::shared_ptr<edsServerHandler> eds = std::make_shared<edsServerHandler>(argv[2]);
    eds->readSensorConfiguration();
    eds->decodeServerData();
    eds->printServerData();
    return 0;
  }

  if(pIdvalue)
  {
    std::shared_ptr<edsServerHandler> eds = std::make_shared<edsServerHandler>(argv[2]);
    eds->readSensorConfiguration();
    eds->decodeServerData();
    eds->printIdValue(argv[3]);
    return 0;
  }

  while(1)
  {
    std::shared_ptr<std::string> ip = com.receiveUDP();
    std::string str = *ip;
    const char * c = str.c_str();

    std::cout<<"\x1B[2J\x1B[H";
    auto start = std::chrono::steady_clock::now();

    for(auto &i : ips )
    {
      edsServers.emplace_back(edsHandler, i);
    }

    for(auto& t : edsServers)
    {
      t.join();
    }

	  edsServers.clear();

    auto end = std::chrono::steady_clock::now();

    std::chrono::duration<double> elapsed_seconds = end-start;
    std::cout<< "Elapsed time (system): " << elapsed_seconds.count() << "s\n";

    //Select and update bin for timedistribution

	 int b = elapsed_seconds.count() * 10;
    int m = (elapsed_seconds.count() * 100 - 10 * b) == 0 ? 0 : 1;
    int binIndex = m == 0 ? b - 1 : b ;

    if(noOfBins <= elapsed_seconds.count()*10)
       binIndex = noOfBins - 1;

    bins.at(binIndex)++;

    //Calculete the table space for the columns
    int biggest = *std::max_element(bins.begin(), bins.end());
    int tableSpace = std::log10(biggest) + 2;

    std::stringstream report("", ios_base::app | ios_base::out);

    int i = 10;
    for_each(bins.begin(), bins.end(), [&tableSpace, &i, &binIndex](int bin)
    {
      string colorLatest = binIndex == (i - 10) ? "\033[1;33m" : "\033[0m";
      if(i%10 == 0)
      {
        cout<<endl<<"\033[1;32m"<<(i/10)-1<<": "<<"\033[1;0m"<<colorLatest<<setw(tableSpace)<<bin<<"";
      }
      else
      {
        cout<<colorLatest<<setw(tableSpace)<<bin<<"";
      }
      i++;
    });

    if(!mem)
      mem=getMemory();

    std::cout<<endl<<endl<<mem<<" Kb increased with "<<getMemory()-mem<<" Kb."<<endl;


    if(exit)
      return 0;
    std::this_thread::sleep_for(60s);

 }
  return 0;
}
