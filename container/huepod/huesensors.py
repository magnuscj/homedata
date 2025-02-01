import subprocess
import time
import json
import logging
from datetime import datetime, timedelta
from urllib.request import urlopen
from itertools import islice

# Create a logger
logger = logging.getLogger('logger')
logger.setLevel(logging.DEBUG)
# Create a file handler
fh = logging.FileHandler('sensorlog.log')
fh.setLevel(logging.ERROR)
# Create a console handler
ch = logging.StreamHandler()
ch.setLevel(logging.DEBUG)
# Create a formatter
formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
fh.setFormatter(formatter)
ch.setFormatter(formatter)
# Add handlers to the logger
logger.addHandler(fh)
logger.addHandler(ch)

encoding = 'utf-8'
i=1
tempSamples = []
humiSamples = []
data = []
sensorTimeSamples = []
battSamples = []
tempSamplesSort = []
tempSamplesMax = []
tempDirSamples = []
ids = []
maxTempSamples = 60*5
maxTempMaxSamples = 60*60
pollCount = 0
errors = 0
success = 0



now = datetime.now()
timestamp = now.strftime("%Y-%m-%d %H:%M:%S")
macdata = subprocess.Popen("ip addr show | grep -m1 ether | awk {'print $2'}", shell=True, stdout=subprocess.PIPE)
mac = "1c:69:7a:02:8c:4c"  #macdata.stdout.read().decode("utf-8")

for x in range(1,14):
    ids.append(mac.strip()+":"+str(x))
logger.debug(ids)

url = "http://192.168.50.237/get_livedata_info?"

urlHue ='curl -sX GET http://192.168.50.151/api/HTymPjBT0g1JdTwXFdYe-N26G9IQ8MDQ8quVIkr1/sensors | jq .\\"'
hueSensors =['14','33','36','80','83']
urlTemperature ='\\".state.temperature'
sLastTime ='\\".state.lastupdated'
sBatt ='\\".config.battery'

while 1:
    pollCount+=1    
    start_time = time.time()
    del tempSamples[:]
    del humiSamples[:]
    del sensorTimeSamples[:]
    del battSamples[:]
       
    for sensor in range(1,5):
        getUrlData = urlHue + sensor + urlTemperature
        print(getUrlData)
        temperatur = subprocess.Popen(getUrlData, shell=True, stdout=subprocess.PIPE)
        
        try:
            temp = str(float(temperatur.stdout.read())/100)
            tempSamples.append(temp)
        except Exception as e:
            logger.error(e)

        getUrlData = urlHue + sensor + sLastTime
        sensorTime = subprocess.Popen(getUrlData, shell=True, stdout=subprocess.PIPE)
        sTime = sensorTime.stdout.read().decode("utf-8").replace('"\n','').replace('"','')

        now = datetime.now() 
        timestamp = now.strftime("%Y-%m-%d %H:%M:%S")
       
        delta = now - datetime.strptime(sTime, "%Y-%m-%dT%H:%M:%S") - timedelta(hours=1)
        sensorTimeSamples.append(str(delta).split('.')[0])

        getUrlData = urlHue + sensor + sBatt
        battery = subprocess.Popen(getUrlData, shell=True, stdout=subprocess.PIPE)
        batt = str(float(battery.stdout.read()))
        battSamples.append(batt)
    
    