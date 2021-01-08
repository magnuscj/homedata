//UDPClient.c

/*
 * gcc -o client UDPClient.c
 * ./client 
 */
#include <iostream>
#include <arpa/inet.h>
#include <netinet/in.h>
#include <stdio.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <unistd.h>
#include <stdlib.h> 
#include <string.h>
#define BUFLEN 1024
#define PORT 9930

using namespace std;

void err(char const *s)
{
    perror(s);
    exit(1);
}

int main(int argc, char** argv)
{
    struct sockaddr_in serv_addr;
    int sockfd, i, slen=sizeof(serv_addr);
    char buf[BUFLEN];

    if(argc != 3)
    {
      printf("Usage : %s <Server-IP> <EDS-IP>\n",argv[0]);
      exit(0);
    }

    if ((sockfd = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP))==-1)
        err("socket");

    bzero(&serv_addr, sizeof(serv_addr));
    serv_addr.sin_family = AF_INET;
    serv_addr.sin_port = htons(PORT);
    if ((inet_aton(argv[1], &serv_addr.sin_addr)==0) && (inet_aton(argv[2], &serv_addr.sin_addr)==0))
    {
        fprintf(stderr, "inet_aton() failed\n");
        exit(1);
    }

    if (sendto(sockfd, argv[2], BUFLEN, 0, (struct sockaddr*)&serv_addr, slen)==-1)
      err("sendto()");

    close(sockfd);
    return 0;
}
