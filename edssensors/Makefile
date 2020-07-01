#all:
#	g++ -Wl,--no-as-needed -lpthread -o eds main.cc ../../tinyxml2/tinyxml2.cpp edsServerHandler.cc `mysql_config --cflags --libs` -lcurl

output: main.o tinyxml2.o edsServerHandler.o
	g++ -Wl,--no-as-needed -lpthread `mysql_config --cflags --libs` -lcurl main.o tinyxml2.o edsServerHandler.o -o eds

main.o: main.cc
	g++ -c main.cc

tinyxml2.o: ../../tinyxml2/tinyxml2.cpp
	g++ -c ../../tinyxml2/tinyxml2.cpp

edsServerHandler.o: edsServerHandler.cc edsServerHandler.h
	g++ -c edsServerHandler.cc

clean:
	rm *.o eds
