#ifndef COMMUNICATION_H_
#define COMMUNICATION_H_

#include <curl/curl.h>
#include <string.h>
#include <arpa/inet.h>
#include <netinet/in.h>
#include <stdio.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <unistd.h>
#include <stdlib.h>
#include <memory>
#define BUFLEN 512
#define PORT 9930
//#include <cstring>

#define FROM_ADDR    "magnus.c.johansson@gmail.com"
#define TO_ADDR      "magnus.c.johansson@gmail.com"
#define CC_ADDR      "magnus.c.johansson@gmail.com"
 
#define FROM_MAIL "Sender Person " FROM_ADDR
#define TO_MAIL   "A Receiver " TO_ADDR
#define CC_MAIL   "John CC Smith " CC_ADDR



class communication
{
    public:
      communication();
      ~communication();
      void sendMail(const char* message);
      std::shared_ptr<std::string> receiveUDP();
      static size_t handlePayload(void *ptr, size_t size, size_t nmemb, void *userp);

    private:
      CURL *curl;
      CURLcode res = CURLE_OK;
      struct upload_status
      {
        int lines_read;
      };

      struct curl_slist *recipients = NULL;
      std::string msg = "";

      struct sockaddr_in my_addr, cli_addr;
      int sockfd, i;
      socklen_t slen=sizeof(cli_addr);
      char buf[BUFLEN];
      char returnVal[512];
};
#endif // COMMUNICATION_H_

