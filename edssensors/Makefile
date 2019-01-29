all:
	g++ -Wl,--no-as-needed -lpthread -o eds main.cc ../../tinyxml2/tinyxml2.cpp edsServerHandler.cc `mysql_config --cflags --libs` -lcurl

compile:
