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
using namespace std;
//using namespace tinyxml2;

void test(char* ipadr )
{
    edsServerHandler eds(ipadr);
    eds.readSensorConfiguration();
    eds.decodeServerData();
    eds.storeServerData();
}

int main(int argc, char* argv[])
{
  std::vector<std::thread> tve;
  std::vector<double> elapsedTime;
  int noOfBins = 40;
  std::vector<int> bins(noOfBins,0);

  edsServerHandler edsConf(argv[1]); 
  
  while(1)//for(int j=0;j<100;j++)//while(1)//
  {
    std::cout << "\x1B[2J\x1B[H";
    auto start = std::chrono::system_clock::now();
    
    for(int i = 1;i < argc;i++)
    {
      tve.emplace_back(test, argv[i]);
    }
    for(auto& t : tve)
    {
      t.join();
    }
    tve.clear();  
    
    auto end = std::chrono::system_clock::now();
   
    std::chrono::duration<double> elapsed_seconds = end-start;
    std::time_t end_time3 = std::chrono::system_clock::to_time_t(end);
    std::cout<< "Elapsed time: " << elapsed_seconds.count() << "s\n";
  
    
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

    for(auto mybin : bins)
      cout<<mybin<<",";
    cout<<"\n";  
    edsConf.readSensorConfiguration();
    
    sleep(60); 
 } 
  
  return 0;
}
