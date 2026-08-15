#!/usr/bin/python3
import RPi.GPIO as GPIO
import time
import os
from urllib.request import urlopen
import json
import logging
import signal
import sys
import threading

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
NAME = 'name'
WET = 'potWet'
DRY = 'potDry'
ACTIVE = 'potActive'
DURATION = 'watering_duration'

MAX_WATERING_PER_CYCLE = 600  # seconds, safety cap per pot
STATUS_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "status.json")
TEST_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "test_request.json")
TEST_DURATION = 20  # seconds

def write_status(active_pots, testing=False, next_measure=None, watering_start=None, watering_duration=None):
    try:
        with open(STATUS_PATH, 'w') as f:
            json.dump({
                "watering": active_pots,
                "testing": testing,
                "next_measure": next_measure,
                "watering_start": watering_start,
                "watering_duration": watering_duration
            }, f)
    except Exception:
        pass

def run_test(pot):
    try:
        logger.info("TEST: Opening valve {} for {} seconds".format(pot, TEST_DURATION))
        write_status([pot], testing=True, watering_start=time.time(), watering_duration=TEST_DURATION)
        GPIO.output(potPin[pot], GPIO.HIGH)
        time.sleep(TEST_DURATION)
        GPIO.output(potPin[pot], GPIO.LOW)
        write_status([], testing=False)
        logger.info("TEST: Valve {} closed".format(pot))
    except Exception as e:
        logger.error("Test error: {}".format(e))
        GPIO.output(potPin[pot], GPIO.LOW)
        write_status([], testing=False)

def check_test_request():
    try:
        if not os.path.exists(TEST_PATH):
            return
        with open(TEST_PATH, 'r') as f:
            req = json.load(f)
        os.remove(TEST_PATH)
        if req.get('all'):
            t = threading.Thread(target=run_mechanics_test, daemon=True)
            t.start()
            return
        pot = int(req.get('pot', -1))
        if pot < 0 or pot >= 8:
            logger.error("Invalid test pot: {}".format(pot))
            return
        t = threading.Thread(target=run_test, args=(pot,), daemon=True)
        t.start()
    except Exception as e:
        logger.error("Test request error: {}".format(e))

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

def getConfig(key, cnf):
    configuration = []
    try:
        for c in cnf:
            configuration.append(int(c[key]))
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

def run_mechanics_test():
    """Test all 8 valves sequentially for 5 seconds each."""
    logger.info("Running full mechanics test")
    for b in range(8):
        GPIO.output(potPin[b], GPIO.HIGH)
        write_status([b], testing=True, watering_start=time.time(), watering_duration=5)
        time.sleep(5)
        GPIO.output(potPin[b], GPIO.LOW)
        time.sleep(0.4)
    write_status([], testing=False)
    logger.info("Mechanics test complete")

def setupBoard():
    GPIO.setmode(GPIO.BOARD)
    logger.info("Setting up board")
    for b in range(8):
        GPIO.setup(potPin[b], GPIO.OUT)
        GPIO.output(potPin[b], GPIO.LOW)

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
        elapsed = 0
        while elapsed < 60:
            check_test_request()
            time.sleep(2)
            elapsed += 2
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
                write_status([b], watering_start=time.time(), watering_duration=duration)
                hyst[b] = 1
                logger.info("Watering pot: {} ({} - Humidity: {}({}/{}))".format(
                    potNo[b], potNames[b] if b < len(potNames) else "?",
                    humidity, potDry[b], potWet[b]))
                elapsed = 0
                while elapsed < duration:
                    check_test_request()
                    time.sleep(2)
                    elapsed += 2
                wateringCycle -= duration
            finally:
                GPIO.output(potPin[b], GPIO.LOW)
                write_status([])
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
    write_status([], next_measure=time.time() + sleepTime)
    elapsed = 0
    while elapsed < sleepTime:
        check_test_request()
        time.sleep(2)
        elapsed += 2
