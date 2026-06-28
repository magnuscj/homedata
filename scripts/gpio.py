#!/usr/bin/python3
import RPi.GPIO as GPIO
import time
from urllib.request import urlopen
import json
import logging
import signal
import sys

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
watDur  = [180,180,180,180,180,180,180,180]
potNames= ["name1","name2","name3","name4","name5","name6","name7","name8"]
hyst    = [0,0,0,0,0,0,0,0]
soilHumidity = []
url = "http://ws-gateway/get_livedata_info?"
NAME = 1
WET = 2
DRY = 3
ACTIVE = 4
DURATION = 5

MAX_WATERING_PER_CYCLE = 600  # seconds, safety cap per pot

logger.debug("Init done")

def signal_handler(sig, frame):
    logger.critical("Controlled exit")
    GPIO.cleanup()
    sys.exit(0)

def readConfig():
    config = []
    try:
        logger.debug("Read configuration file")
        with open("config.json", "r") as configFile:
            config_json = json.loads(configFile.read())
            config = config_json['potConfig']
    except Exception as e:
        logger.error(e)
        logger.error("Configuration could not be read")
    return config

def getConfig(item, cnf):
    configuration = []
    try:
        for c in cnf:
            pv = list(c.values())
            configuration.append(int(pv[item]))
    except Exception as e:
        logger.error(e)
    logger.debug(configuration)
    return configuration

def measureHumidity(url):
    del soilHumidity[:]
    data = []
    logger.debug("Collect soil moisture measurements.")
    try:
        response = urlopen(url, timeout=10)
        data_json = json.loads(response.read())
        data = data_json['ch_soil']
    except Exception as e:
        logger.error("Couldn't reach soil measurement server.")
        logger.error(e)
        return soilHumidity

    for x in data:
        try:
            humVal = x['humidity'].replace('%', '')
            soilHumidity.append(humVal)
        except (KeyError, AttributeError) as e:
            logger.error("Unexpected soil data format: {}".format(e))
            soilHumidity.append("--")
    return soilHumidity

def getPotNames(url):
    del potNames[:]
    data = []
    logger.debug("Collect soil sensor names.")
    try:
        response = urlopen(url, timeout=10)
        data_json = json.loads(response.read())
        data = data_json['ch_soil']
    except Exception as e:
        logger.error("Couldn't reach soil measurement server.")
        logger.error(e)
        return potNames

    for x in data:
        try:
            potNames.append(x.get('name', ''))
        except Exception as e:
            logger.error("Unexpected name format: {}".format(e))
            potNames.append('')
    return potNames

def setupBoard():
    GPIO.setmode(GPIO.BOARD)
    logger.info("Setting up board")
    for b in range(8):
        GPIO.setup(potPin[b], GPIO.OUT)
        GPIO.output(potPin[b], GPIO.LOW)

    logger.info("Test watering mechanics")
    for b in range(8):
        GPIO.output(potPin[b], GPIO.HIGH)
        time.sleep(0.1)
        time.sleep(5)
        GPIO.output(potPin[b], GPIO.LOW)

    for b in range(8):
        GPIO.output(potPin[b], GPIO.LOW)
        time.sleep(0.1)

signal.signal(signal.SIGINT, signal_handler)
setupBoard()

logger.info("Starting")
while True:
    config = readConfig()

    try:
        potWet = getConfig(WET, config)
        potDry = getConfig(DRY, config)
        potAct = getConfig(ACTIVE, config)
        watDur = getConfig(DURATION, config)
    except Exception as e:
        logger.error(e)
        logger.warning("Going for emergency values")
        potWet  = [45,45,45,45,45,45,45,45]
        potDry  = [35,35,35,35,35,35,35,35]
        potAct  = [1,1,1,1,1,1,1,1]
        watDur  = [60,60,60,60,60,60,60,60]

    potNames = getPotNames(url)

    logger.info("Time for measure")
    soilHumidity = measureHumidity(url)

    if not soilHumidity:
        logger.warning("No humidity data — skipping watering cycle")
        time.sleep(60)
        continue

    logger.debug("Pot name:      {}".format(' '.join(map(str, potNames))))
    logger.debug("Pot humidity:  {}".format('  '.join(map(str, soilHumidity))))
    logger.debug("Pot dry level: {}".format('  '.join(map(str, potDry))))
    logger.debug("Pot wet level: {}".format('  '.join(map(str, potWet))))

    noOfPots = min(len(soilHumidity), 8)

    wateringCycle = 1440
    logger.info("Time for watering")
    for b in range(noOfPots):
        if str(soilHumidity[b]) == "--":
            soilHumidity[b] = 99
        if not potAct[b]:
            continue
        try:
            humidity = int(soilHumidity[b])
        except ValueError:
            logger.error("Invalid humidity value for pot {}: {}".format(potNo[b], soilHumidity[b]))
            continue

        if humidity <= potDry[b] or hyst[b] == 1:
            duration = min(watDur[b], MAX_WATERING_PER_CYCLE)
            try:
                GPIO.output(potPin[b], GPIO.HIGH)
                hyst[b] = 1
                logger.info("Watering pot: {} ({} - Humidity: {}({}/{}))".format(
                    potNo[b], potNames[b] if b < len(potNames) else "?",
                    humidity, potDry[b], potWet[b]))
                time.sleep(duration)
                wateringCycle -= duration
            finally:
                GPIO.output(potPin[b], GPIO.LOW)
            time.sleep(1/3)

        if humidity >= potWet[b] and humidity != 99:
            hyst[b] = 0
            GPIO.output(potPin[b], GPIO.LOW)
            logger.info("The pot is too wet: {} ({} - Humidity: {}({}/{}))".format(
                potNo[b], potNames[b] if b < len(potNames) else "?",
                humidity, potDry[b], potWet[b]))

    logger.info("Watering done")
    sleepTime = max(wateringCycle, 60)
    logger.info("Let's sleep for {} seconds.".format(sleepTime))
    time.sleep(sleepTime)
