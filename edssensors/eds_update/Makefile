#all:
#	g++ -Wl,--no-as-needed -lpthread -o eds main.cc ../../tinyxml2/tinyxml2.cpp edsServerHandler.cc `mysql_config --cflags --libs` -lcurl
output: eds_update.o
	g++ -g -Wl,--no-as-needed -lpthread eds_update.o -o eds_update

eds_update.o: eds_update.cc
	g++ -g -c eds_update.cc

clean:
	rm *.o eds_update
