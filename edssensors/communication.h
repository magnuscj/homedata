#ifndef COMMUNICATION_H_
#define COMMUNICATION_H_

#include <curl/curl.h>
#include <string.h>
#include <string>
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
        const char* to_mail;
        const char* from_mail;
        const char* cc_mail;
      };

      struct curl_slist *recipients = NULL;
      std::string msg = "";
      std::string smtpUser;
      std::string smtpPwd;
      std::string smtpFrom;
      std::string smtpTo;

      struct sockaddr_in my_addr, cli_addr;
      int sockfd, i;
      socklen_t slen=sizeof(cli_addr);
      char buf[BUFLEN];
      char returnVal[512];
};
#endif // COMMUNICATION_H_

