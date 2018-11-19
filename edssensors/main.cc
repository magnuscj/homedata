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

using namespace std;

void test(char* ipadr )
{
    edsServerHandler eds(ipadr);
    eds.readSensorConfiguration();
    eds.decodeServerData();
    eds.storeServerData();
    cout<<eds;
}

int main(int argc, char* argv[])
{
  std::vector<std::thread> tve;
  std::vector<double> elapsedTime;
  int noOfBins = 5*10;
  std::vector<int> bins(noOfBins,0);
  
  for(int j=0;j<10;j++)//while(1)//
  {
    std::cout << "\x1B[2J\x1B[H";
    auto start = std::chrono::steady_clock::now();

    for(int i = 1;i < argc;i++)
    {
      tve.emplace_back(test, argv[i]);
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
        cout<<setw(tableSpace)<<mybin<<"\n"<<s<<": ";
        i=0;
      }
      else
        cout<<setw(tableSpace)<<mybin<<"";
      i++;
    }
    sleep(60); 
 } 
  
  return 0;
}
