#!/usr/bin/pythoin3
import RPi.GPIO as GPIO
import time
from urllib.request import urlopen
from itertools import islice
import json
import array

potPin  = [11,13,15,19,21,23,37,29]
potNo   = ["1","2","3","4","5","6","7","8"]
potWet  = [45,45,45,45,45,45,45,45]
potDry  = [35,35,35,35,35,35,35,35]
potAct  = [1,1,1,1,1,1,1,1]
potNames= []
hyst    = [0,0,0,0,0,0,0,0]
soilHumidity = []
data = []
dry = 35
wet = 45
url = "http://ws-gateway/get_livedata_info?"

try:
    configFile = open("config.json", "r")
    config_json = json.loads(configFile.read())
    config  = config_json['potConfig']
except:
    print("ConfigFile not found")

del potWet[:]
del potDry[:]
del potAct[:]

for c in config:
    pv = c.values()
    potWet.append(int(list(islice(pv,0,4))[2]))
    potDry.append(int(list(islice(pv,0,4))[3]))
    potAct.append(int(list(islice(pv,0,5))[4]))

GPIO.setmode(GPIO.BOARD)

for b in range(8):
    GPIO.setup(potPin[b], GPIO.OUT)
    GPIO.output(potPin[b], GPIO.LOW)

for b in range(8):
    GPIO.output(potPin[b], GPIO.HIGH)
    time.sleep(100/1000)

time.sleep(2)

for b in range(8):
    GPIO.output(potPin[b], GPIO.LOW)
    time.sleep(100/1000)

i=0
while(1):
    del soilHumidity[:]
    del potNames[:]
    try:
        response = urlopen(url)
        data_json = json.loads(response.read())
        data  = data_json['ch_soil']
    except:
        print(url + " not found")
    
    #Check the humidity
    for x in data:
        keys = x.keys()
        values = x.values()
        humVal = list(islice(values,0,4))[3].replace('%','')
        soilHumidity.append(humVal)
        potNames.append(list(islice(values,0,4))[1])

    print("Pot names:     ", potNames)    
    print("Pot hunidity:  ", soilHumidity)
    print("Pot dry level: ", potDry)
    print("Pot wet level: ", potWet)
    noOfPots =len(soilHumidity)

    #Act on humidity
    for b in range(noOfPots):
        if(potAct[b]):
            if(int(soilHumidity[b]) < potDry[b] or hyst[b]==1):
                GPIO.output(potPin[b], GPIO.HIGH)
                hyst[b]=1
                print("Vattnar kruka: " + potNo[b] + " ("+potNames[b]+")")
            if(int(soilHumidity[b]) > potWet[b]):
                hyst[b]=0
                GPIO.output(potPin[b], GPIO.LOW)
                print("Slutar vattnar kruka: " + potNo[b] + " ("+potNames[b]+")")
            time.sleep(200/1000)
    time.sleep(60)
    if(i > 10):
        for b in range(noOfPots):
            GPIO.output(potPin[b], GPIO.LOW)
            print("Slutar vattnar kruka: " + potNo[b] + " ("+potNames[b]+")")
        time.sleep(200/1000)
        i=0
    i=i+1
GPIO.cleanup()
