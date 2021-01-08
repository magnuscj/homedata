#all:
#	g++ -Wl,--no-as-needed -lpthread -o eds main.cc ../../tinyxml2/tinyxml2.cpp edsServerHandler.cc `mysql_config --cflags --libs` -lcurl
output: main.o tinyxml2.o edsServerHandler.o communication.o
	g++ -g -Wl,--no-as-needed -lpthread `mysql_config --cflags --libs` -lcurl main.o tinyxml2.o edsServerHandler.o communication.o -o eds

main.o: main.cc
	g++ -g -c main.cc

tinyxml2.o: ../../tinyxml2/tinyxml2.cpp
	g++ -g -c ../../tinyxml2/tinyxml2.cpp

edsServerHandler.o: edsServerHandler.cc edsServerHandler.h
	g++ -g -c edsServerHandler.cc

communication.o: communication.cc communication.h
	g++ -g -c communication.cc

clean:
	rm *.o eds