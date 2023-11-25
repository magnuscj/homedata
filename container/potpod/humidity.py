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

humiSamples = []
data = []

ids = []
pollCount = 0
errors = 0
success = 0



now = datetime.now()
timestamp = now.strftime("%Y-%m-%d %H:%M:%S")
macdata = subprocess.Popen("ip addr show | grep -m1 ether | awk {'print $2'}", shell=True, stdout=subprocess.PIPE)
mac = macdata.stdout.read().decode("utf-8")

for x in range(1,13):
    ids.append(mac.strip()+":"+str(x))
logger.debug(ids)

url = "http://192.168.1.237/get_livedata_info?"

while 1:
    pollCount+=1    
    start_time = time.time()
    
    del humiSamples[:]
    
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
        with open(r'tmpl_humidetails.xml', 'r') as file:
            data = file.read()
            file.close()
            
            for i in range(len(humiSamples)):
                data = data.replace("#HUMI"+str(i)+"#", humiSamples[i])

            data = data.replace("#LOOPTIME#", str(time.time() - start_time))
            data = data.replace("#MAC#", mac)
            
            for i in range(5,len(ids)):
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
