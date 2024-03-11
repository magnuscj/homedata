#!/usr/bin/pythoin3
import RPi.GPIO as GPIO
import time
import datetime
from urllib.request import urlopen
from itertools import islice
import json
import array
import logging

# Create a logger
logger = logging.getLogger('logger')
logger.setLevel(logging.DEBUG)
formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')

# Create a file handler
fh = logging.FileHandler('sensorlog.log')
fh.setLevel(logging.ERROR)
fh.setFormatter(formatter)

# create console handler and set level to debug
ch = logging.StreamHandler()
ch.setFormatter(formatter)
ch.setLevel(logging.DEBUG)

# Add handlers to the logger
logger.addHandler(fh)
logger.addHandler(ch)

potPin  = [11,13,15,19,21,23,37,29]
potNo   = ["1","2","3","4","5","6","7","8"]
potWet  = [45,45,45,45,45,45,45,45]
potDry  = [35,35,35,35,35,35,35,35]
potAct  = [1,1,1,1,1,1,1,1]
watDur  = [60,60,60,60,60,60,60,60]
potNames= []
hyst    = [0,0,0,0,0,0,0,0]
soilHumidity = []
data = []
dry = 35
wet = 45
url = "http://ws-gateway/get_livedata_info?"
NAME = 1
WET = 2
DRY = 3
ACTIVE = 4
DURATION = 5

logger.debug("Init done")

def readConfig():
    try:
        logger.debug("Read configuration file")
        configFile = open("config.json", "r")
        config_json = json.loads(configFile.read())
        config  = config_json['potConfig']
    except Exception as e:
        logger.error(e)
        logger.error("Configuration file not found")
    return config

def getConfig(item, cnf):
    configuration = []
    try:
        for c in cnf:
            pv = c.values()
            configuration.append(int(list(islice(pv,0,6))[item]))
    except Exception as e:
        logger.error(e)
    logger.debug(configuration)
    return configuration

config = readConfig()

del potWet[:]
del potDry[:]
del potAct[:]
del watDur[:]

try:
    for c in config:
        pv = c.values()
        potWet = getConfig(WET, config) #.append(int(list(islice(pv,0,4))[2]))
        potDry = getConfig(DRY, config) #.append(int(list(islice(pv,0,4))[3]))
        potAct = getConfig(ACTIVE, config) #.append(int(list(islice(pv,0,5))[4]))
        watDur = getConfig(DURATION, config) #.append(int(list(islice(pv,0,6))[5]))
except Exception as e:
    logger.error(e)
    logger.warning("Going for emergency values")
    potWet  = [45,45,45,45,45,45,45,45]
    potDry  = [35,35,35,35,35,35,35,35]
    potAct  = [1,1,1,1,1,1,1,1]
    watDur  = [60,60,60,60,60,60,60,60]

GPIO.setmode(GPIO.BOARD)

logger.info("Setting up board")
for b in range(8):
    GPIO.setup(potPin[b], GPIO.OUT)
    GPIO.output(potPin[b], GPIO.LOW)

logger.info("Test channels")
for b in range(8):
    #GPIO.output(potPin[b], GPIO.HIGH)
    time.sleep(100/1000)

time.sleep(2)

for b in range(8):
    #GPIO.output(potPin[b], GPIO.LOW)
    time.sleep(100/1000)

logger.info("Starting")
while(1):
    config = readConfig()
    potWet = getConfig(WET, config)
    potDry = getConfig(DRY, config)
    potAct = getConfig(ACTIVE, config)
    watDur = getConfig(DURATION, config)

    del soilHumidity[:]
    del potNames[:]
    try:
        response = urlopen(url)
        data_json = json.loads(response.read())
        data  = data_json['ch_soil']
    except  Exception as e:
        logger.error("Couldn't reach soil measurement server.")
        logger.error(e)
    
    #Check the humidity
    for x in data:
        keys = x.keys()
        values = x.values()
        humVal = list(islice(values,0,4))[3].replace('%','')
        soilHumidity.append(humVal)
        potNames.append(list(islice(values,0,4))[1])

    logger.debug("Pot name:      {}".format(' '.join(map(str, potNames))))
    logger.debug("Pot humidity:  {}".format('  '.join(map(str, soilHumidity))))
    logger.debug("Pot dry level: {}".format('  '.join(map(str, potDry))))
    logger.debug("Pot wet level: {}".format('  '.join(map(str, potWet))))
    noOfPots =len(soilHumidity)
    
    #Act on humidity
    try:
        if ((int(datetime.datetime.now().minute) % 59) == 0):
            logger.info("Time for measure")
            for b in range(noOfPots):
                if(potAct[b]):
                    if(int(soilHumidity[b]) < potDry[b] or hyst[b]==1):
                        GPIO.output(potPin[b], GPIO.HIGH)
                        hyst[b]=1
                        logger.info("Watering pot: " + potNo[b] + " (" + potNames[b] + " - Humidity: " + str(soilHumidity[b]) + "(" + str(potDry[b]) + "/" + str(potWet[b]) + ")")
                        time.sleep(watDur[b])
                        GPIO.output(potPin[b], GPIO.LOW)
                        time.sleep(1/3)
                    if(int(soilHumidity[b]) > potWet[b]):
                        hyst[b]=0
                        GPIO.output(potPin[b], GPIO.LOW)
                        logger.info("The pot is too wet: " + potNo[b] + " ("+potNames[b]+" - Humidity: " + str(soilHumidity[b]) + "(" + str(potDry[b]) + "/" + str(potWet[b]) + ")")
            time.sleep(61)
        time.sleep(55)
    except Exception as e:
        logger.error("Couldn't act on humidity properly")
        logger.error(e)
        GPIO.cleanup()

GPIO.cleanup()
