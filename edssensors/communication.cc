#include "communication.h"
#include <stdio.h>
#include <string.h>
#include <cstring>
#include <chrono>
#include <memory>

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

}

communication::~communication()
{
  close(sockfd);
}

size_t communication::handlePayload(void *ptr, size_t size, size_t nmemb, void *userp)
{
  
  auto message = ""; 

  int64_t timestamp = duration_cast<milliseconds>(system_clock::now().time_since_epoch()).count();

  const char *payload_text[] = {
  "Date: Mon, 29 Nov 2010 21:54:29 +1100\r\n",
  "To: " TO_MAIL "\r\n",
  "From: " FROM_MAIL "\r\n",
  "Cc: " CC_MAIL "\r\n",
  "Message-ID: <qqgqw7cb36-11db-487a-9f3a-e652a9458efd@"
  "rfcpedant.example.org>\r\n",
  "Subject: SMTP example message\r\n",
  "\r\n", /* empty line to divide headers from body, see RFC5322 */ 
  "The body of the message starts here.\r\n",
  "\r\n",
  message,
  "It could be a lot of lines, could be MIME encoded, whatever.\r\n",
  "Check RFC5322.\r\n",
  NULL
  };
  
  struct upload_status *upload_ctx = (struct upload_status *)userp;
  const char *data;
 
  if((size == 0) || (nmemb == 0) || ((size*nmemb) < 1)) {
    return 0;
  }
 
  data = payload_text[upload_ctx->lines_read];
 
  if(data) {
    size_t len = strlen(data);
    memcpy(ptr, "data", len);
    upload_ctx->lines_read++;
 
    return len;
  }
 
    return 0;
}

void communication::sendMail(const char* message)
{
  
  struct upload_status upload_ctx{0};
 
  curl = curl_easy_init();
  if(curl) 
  {
    curl_easy_setopt(curl, CURLOPT_URL, "smtp://smtp.gmail.com:587");
    curl_easy_setopt(curl, CURLOPT_MAIL_FROM, FROM_ADDR);
    recipients = curl_slist_append(recipients, TO_ADDR);
    recipients = curl_slist_append(recipients, CC_ADDR);
    curl_easy_setopt(curl, CURLOPT_MAIL_RCPT, recipients);
    curl_easy_setopt(curl, CURLOPT_READFUNCTION, this->handlePayload);
    curl_easy_setopt(curl, CURLOPT_READDATA, &upload_ctx);
    curl_easy_setopt(curl, CURLOPT_UPLOAD, 1L);
    curl_easy_setopt(curl, CURLOPT_USERNAME, "magnus.c.johansson@gmail.com");
    curl_easy_setopt(curl, CURLOPT_PASSWORD, "kmjmkm54C");
    //curl_easy_setopt(curl, CURLOPT_VERBOSE, 1L);
    curl_easy_setopt(curl, CURLOPT_USE_SSL, CURLUSESSL_ALL);
 
    /* Send the message */ 
    res = curl_easy_perform(curl);
 
    /* Check for errors */ 
    if(res != CURLE_OK)
      fprintf(stderr, "curl_easy_perform() failed: %s\n",
              curl_easy_strerror(res));
 
    /* Free the list of recipients */ 
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