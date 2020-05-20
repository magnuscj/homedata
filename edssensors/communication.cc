#include "communication.h"
#include <stdio.h>
#include <string.h>
#include <cstring>
#include <chrono>
using namespace std;
using namespace std::chrono;

communication::communication()
{}

communication::~communication()
{}

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
