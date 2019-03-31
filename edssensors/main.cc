#include <iostream>
#include <string.h>
#include <time.h>
#include "edsServerHandler.h"
#include <thread>
#include <chrono>
#include <memory>
#include <algorithm>
#include <vector>
#include <unistd.h>
#include <iomanip>
#include <sstream>
#include <fstream>
using namespace std;


void edsHandler(char* ipadr )
{
    std::shared_ptr<edsServerHandler> eds = std::make_shared<edsServerHandler>(ipadr);
    eds->readSensorConfiguration();
    eds->decodeServerData();
    eds->storeServerData();
    cout<<*eds;
}

int parseLine(char* line){
    // This assumes that a digit will be found and the line ends in " Kb".
    int i = strlen(line);
    const char* p = line;
    while (*p <'0' || *p > '9') p++;
    line[i-3] = '\0';
    i = atoi(p);
    return i;
}

int getValue(){ //Note: this value is in KB!
    FILE* file = fopen("/proc/self/status", "r");
    int result = -1;
    char line[128];

    while (fgets(line, 128, file) != NULL){
        if (strncmp(line, "VmRSS:", 6) == 0){
            result = parseLine(line);
            break;
        }
    }
    fclose(file);
    return result;
}

int main(int argc, char* argv[])
{
  std::vector<std::thread> tve;
  std::vector<double> elapsedTime;
  int mem = 0;
  int noOfBins = 5*10;
  std::vector<int> bins(noOfBins,0);
  
  while(1)//for(int j=0;j<10;j++)////
  {
    std::cout<<"\x1B[2J\x1B[H";
    auto start = std::chrono::steady_clock::now();
    for(int i = 1;i < argc;i++)
    {
      tve.emplace_back(edsHandler, argv[i]);
      //edsHandler(argv[i]);
    }
    for(auto& t : tve)
    {
      t.join();
    }
    tve.clear();  
    
    auto end = std::chrono::steady_clock::now();

    std::chrono::duration<double> elapsed_seconds = end-start;
    std::cout<< "Elapsed time (system): " << elapsed_seconds.count() << "s\n";
    
    double step = 0.1;
    double bin = 0;
    for(int i=0;i<noOfBins;i++)
    { 
      if(elapsed_seconds.count()>bin && elapsed_seconds.count()<(bin + step))
      {
        bins[i]++;
      }
      bin = bin + step;
    }

    int i=1;
    int s=1;
    int tableSpace = 6;
    int biggest = 0;

    for(auto mybin : bins)
    {
      biggest = mybin > biggest ? mybin : biggest;
    }
    
    for(int i = 10000;i>=1;i=i/10)
    {
      tableSpace = (biggest/i) ? tableSpace : tableSpace-1;
    }
    
    cout<<"\n"<<s<<": ";
    for(auto mybin : bins)
    {
      if(i/10)
      {
        s++;
        cout<<setw(tableSpace)<<mybin<<endl<<s<<": ";
        i=0;
      }
      else
        cout<<setw(tableSpace)<<mybin<<"";
      i++;
    }
    
    if(!mem)
      mem=getValue();
    std::cout<<endl<<mem<<" Kb increased with "<<getValue()-mem<<" Kb."<<endl;
    std::this_thread::sleep_for(60s);
 } 
  
  return 0;
}
