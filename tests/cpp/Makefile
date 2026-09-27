# tests/cpp/Makefile — build & run the C++ unit tests (Catch2, header-only).
#
# We reuse the object files from ../../edssensors so the tests exercise the real
# edsServerHandler translation unit. decodeXml pulls in tinyxml2; edsServerHandler
# is also linked with MySQL/curl because the .o references those symbols, even
# though the unit tests never open a DB connection or a socket.

EDS_DIR   := ../../edssensors
TINYXML2  := ../../../tinyxml2/tinyxml2.cpp

CXX      := g++
CXXFLAGS := -g -std=c++17 -I$(EDS_DIR) $(shell mysql_config --cflags) -DFIXTURE_DIR='"$(CURDIR)/fixtures"'
LDLIBS   := $(shell mysql_config --libs) -lcurl -lpthread

# Object files from the main build we link against.
EDS_OBJS := $(EDS_DIR)/edsServerHandler.o tinyxml2.o

TESTS    := $(wildcard test_*.cc)
BINS     := $(TESTS:.cc=)

.PHONY: test clean
test: $(BINS)
	@rc=0; \
	for b in $(BINS); do \
	  echo "--- running $$b ---"; \
	  ./$$b || rc=1; \
	done; \
	exit $$rc

# Build a local tinyxml2.o here so we don't depend on the main build having run it.
tinyxml2.o: $(TINYXML2)
	$(CXX) $(CXXFLAGS) -c $(TINYXML2) -o tinyxml2.o

# Ensure edsServerHandler.o exists (build via the edssensors Makefile).
$(EDS_DIR)/edsServerHandler.o:
	$(MAKE) -C $(EDS_DIR) edsServerHandler.o

# Each test binary = its .cc + the shared eds objects.
test_%: test_%.cc catch.hpp $(EDS_OBJS)
	$(CXX) $(CXXFLAGS) $< $(EDS_OBJS) $(LDLIBS) -o $@

clean:
	rm -f $(BINS) tinyxml2.o
