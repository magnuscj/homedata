#ifndef COMMUNICATION_H_
#define COMMUNICATION_H_

#include <curl/curl.h>
#include <string>
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

};
#endif // COMMUNICATION_H_

