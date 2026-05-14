#include "communication.h"
#include <stdio.h>
#include <string.h>
#include <string>
#include <cstring>
#include <chrono>
#include <memory>
#include <fstream>
#include <sstream>

#include <arpa/inet.h>
#include <netinet/in.h>
#include <stdio.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <unistd.h>
#include <stdlib.h>

using namespace std;
using namespace std::chrono;

void err(char const *str)
{
    perror(str);
    //exit(1);
}

communication::communication()
{
  if ((sockfd = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP))==-1)
  {
    char const *msg = "socket";
    err(msg);
  }
  
  bzero(&my_addr, sizeof(my_addr));
  my_addr.sin_family = AF_INET;
  my_addr.sin_port = htons(PORT);
  my_addr.sin_addr.s_addr = htonl(INADDR_ANY);

  if (bind(sockfd, (struct sockaddr* ) &my_addr, sizeof(my_addr))==-1)
    err("bind");

  std::ifstream infile("edsServerHandlerConf.txt");
  std::string line, item, value;
  while (std::getline(infile, line))
  {
    std::istringstream iss(line);
    if (iss >> item >> value)
    {
      if      (item == "smtp_user") smtpUser = value;
      else if (item == "smtp_pwd")  smtpPwd  = value;
      else if (item == "smtp_from") smtpFrom = value;
      else if (item == "smtp_to")   smtpTo   = value;
    }
  }
}

communication::~communication()
{
  close(sockfd);
}

size_t communication::handlePayload(void *ptr, size_t size, size_t nmemb, void *userp)
{
  struct upload_status *upload_ctx = (struct upload_status *)userp;

  if((size == 0) || (nmemb == 0) || ((size*nmemb) < 1))
    return 0;

  const char *payload_text[] = {
    "Date: Mon, 29 Nov 2010 21:54:29 +1100\r\n",
    upload_ctx->to_mail,
    upload_ctx->from_mail,
    upload_ctx->cc_mail,
    "Message-ID: <qqgqw7cb36-11db-487a-9f3a-e652a9458efd@rfcpedant.example.org>\r\n",
    "Subject: SMTP example message\r\n",
    "\r\n",
    "The body of the message starts here.\r\n",
    "\r\n",
    "It could be a lot of lines, could be MIME encoded, whatever.\r\n",
    "Check RFC5322.\r\n",
    NULL
  };

  const char *data = payload_text[upload_ctx->lines_read];
  if(data) {
    size_t len = strlen(data);
    memcpy(ptr, data, len);
    upload_ctx->lines_read++;
    return len;
  }
  return 0;
}

void communication::sendMail(const char* message)
{
  std::string to_hdr   = "To: "   + smtpTo   + "\r\n";
  std::string from_hdr = "From: " + smtpFrom + "\r\n";
  std::string cc_hdr   = "Cc: "   + smtpTo   + "\r\n";

  struct upload_status upload_ctx{0, to_hdr.c_str(), from_hdr.c_str(), cc_hdr.c_str()};

  curl = curl_easy_init();
  if(curl) 
  {
    curl_easy_setopt(curl, CURLOPT_URL, "smtp://smtp.gmail.com:587");
    curl_easy_setopt(curl, CURLOPT_MAIL_FROM, smtpFrom.c_str());
    recipients = curl_slist_append(recipients, smtpTo.c_str());
    curl_easy_setopt(curl, CURLOPT_MAIL_RCPT, recipients);
    curl_easy_setopt(curl, CURLOPT_READFUNCTION, this->handlePayload);
    curl_easy_setopt(curl, CURLOPT_READDATA, &upload_ctx);
    curl_easy_setopt(curl, CURLOPT_UPLOAD, 1L);
    curl_easy_setopt(curl, CURLOPT_USERNAME, smtpUser.c_str());
    curl_easy_setopt(curl, CURLOPT_PASSWORD, smtpPwd.c_str());
    curl_easy_setopt(curl, CURLOPT_USE_SSL, CURLUSESSL_ALL);

    res = curl_easy_perform(curl);

    if(res != CURLE_OK)
      fprintf(stderr, "curl_easy_perform() failed: %s\n", curl_easy_strerror(res));

    curl_slist_free_all(recipients);
    curl_easy_cleanup(curl);
  }
}

std::shared_ptr<string> communication::receiveUDP()
{
  std::shared_ptr<string> p1(new string(""));
  fd_set readfds, masterfds;
  struct timeval timeout;
  timeout.tv_sec = 1;                    /*set the timeout to 10 seconds*/
  timeout.tv_usec = 0;
  FD_ZERO(&masterfds);
  FD_SET(sockfd, &masterfds);
  memcpy(&readfds, &masterfds, sizeof(fd_set));

  if (select(sockfd+1, &readfds, NULL, NULL, &timeout) < 0)
  {
    perror("on select");
    return p1;
    exit(1);
  }

  if (FD_ISSET(sockfd, &readfds))
  {
    if (recvfrom(sockfd, buf, BUFLEN, 0, (struct sockaddr*)&cli_addr, &slen)==-1)
      err("recvfrom()");
    printf("Received packet from %s:%d\nData: %s\n\n",
          inet_ntoa(cli_addr.sin_addr), ntohs(cli_addr.sin_port), buf);
    
    std::string s(buf);
    *p1=s;
  }
  return p1;
}