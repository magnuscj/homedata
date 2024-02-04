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

url = "http://192.168.1.237/get_livedata_info?"

urlHue ='curl -sX GET http://192.168.1.151/api/HTymPjBT0g1JdTwXFdYe-N26G9IQ8MDQ8quVIkr1/sensors | jq .\\"'
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
       
    for sensor in hueSensors:
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
    
    logger.debug("Temperature samples")
    logger.debug(tempSamples)
    logger.debug("Battery samples")
    logger.debug(battSamples)
    logger.debug("Delta time samples")
    logger.debug(sensorTimeSamples)
    
    try:
        response = urlopen(url)
        data_json = json.loads(response.read())
        data  = data_json['ch_soil']
    except Exception as e:
        logger.error(url)
        logger.error(e)
        
    for x in data:
        keys = x.keys()
        values = x.values()
        name = list(islice(values,0,4))[1]
        humVal = list(islice(values,0,4))[3].replace('%','')
        humiSamples.append(humVal)
    logger.debug("Soil samples")
    logger.debug(humiSamples)

    try:
        with open(r'tmpl_Huedetails.xml', 'r') as file:
            data = file.read()
            file.close()
            data = data.replace("#TIME#", timestamp)
            for i in range(len(tempSamples)):
                data = data.replace("#TEMP"+str(i)+"#", tempSamples[i])
            
            for i in range(len(humiSamples)):
                try:
                    data = data.replace("#HUMI"+str(i)+"#", humiSamples[i])
                except Exception as e:
                    logger.error(e)

            data = data.replace("#LOOPTIME#", str(time.time() - start_time))
            data = data.replace("#MAC#", mac)
            for i in range(len(battSamples)):
                data = data.replace("#BATT"+str(i)+"#", battSamples[i])
            
            data = data.replace("#UPDATE1#", sensorTimeSamples[0])
            data = data.replace("#UPDATE2#", sensorTimeSamples[1])
            data = data.replace("#UPDATE3#", sensorTimeSamples[2])
            data = data.replace("#UPDATE4#", sensorTimeSamples[3])
            data = data.replace("#UPDATE5#", sensorTimeSamples[4])

            for i in range(0,len(ids)):
                data = data.replace("#ID"+str(i+1)+"#",ids[i].rstrip())

            data = data.replace("#POLLCOUNT#", str(pollCount))
            data = data.replace("#ERRORS#", str(errors))
        with open(r'/mnt/ramdisk/details.xml', 'w') as file:
            file.write(data) 
            file.close()
            success+=1
            time.sleep(600)

    except Exception as e:
        errors+=1
        est = timestamp + " " + str(e)
        print(est)
    i=0
