import subprocess
import time
from datetime import datetime, timedelta

encoding = 'utf-8'
i=1
tempSamples = []
tempSamplesSort = []
tempSamplesMax = []
tempDirSamples = []
maxTempSamples = 60*5
maxTempMaxSamples = 60*60
pollCount = 0
errors = 0
success = 0



now = datetime.now()
timestamp = now.strftime("%Y-%m-%d %H:%M:%S")
macdata = subprocess.Popen("ip addr show | grep -m1 ether | awk {'print $2'}", shell=True, stdout=subprocess.PIPE)
mac = macdata.stdout.read().decode("utf-8")
id1 = mac.strip()+":1"
id2 = mac.strip()+":2"
id3 = mac.strip()+":3"

while 1:
    pollCount+=1    
    start_time = time.time()
    del tempSamples[:]
    temperatur = subprocess.Popen('curl -sX GET http://192.168.1.151/api/HTymPjBT0g1JdTwXFdYe-N26G9IQ8MDQ8quVIkr1/sensors | jq .\\"14\\".state.temperature', shell=True, stdout=subprocess.PIPE)
    temp = str(float(temperatur.stdout.read())/100)
    tempSamples.append(temp)

    temperatur = subprocess.Popen('curl -sX GET http://192.168.1.151/api/HTymPjBT0g1JdTwXFdYe-N26G9IQ8MDQ8quVIkr1/sensors | jq .\\"33\\".state.temperature', shell=True, stdout=subprocess.PIPE)
    temp = str(float(temperatur.stdout.read())/100)
    tempSamples.append(temp)

    temperatur = subprocess.Popen('curl -sX GET http://192.168.1.151/api/HTymPjBT0g1JdTwXFdYe-N26G9IQ8MDQ8quVIkr1/sensors | jq .\\"36\\".state.temperature', shell=True, stdout=subprocess.PIPE)
    temp = str(float(temperatur.stdout.read())/100)
    tempSamples.append(temp)


    sensorTime = subprocess.Popen('curl -sX GET http://192.168.1.151/api/HTymPjBT0g1JdTwXFdYe-N26G9IQ8MDQ8quVIkr1/sensors | jq .\\"14\\".state.lastupdated', shell=True, stdout=subprocess.PIPE)

    battery = subprocess.Popen('curl -sX GET http://192.168.1.151/api/HTymPjBT0g1JdTwXFdYe-N26G9IQ8MDQ8quVIkr1/sensors | jq .\\"14\\".config.battery', shell=True, stdout=subprocess.PIPE)

    batt = str(float(battery.stdout.read()))
    try:
        now = datetime.now()
        timestamp = now.strftime("%Y-%m-%d %H:%M:%S")
        sTime = sensorTime.stdout.read().decode("utf-8").replace('"\n','').replace('"','')
        delta = now - datetime.strptime(sTime, "%Y-%m-%dT%H:%M:%S") - timedelta(hours=2)


        with open(r'tmpl_Huedetails.xml', 'r') as file:
            data = file.read()
            file.close()
            data = data.replace("#TIME#", timestamp)
            data = data.replace("#TEMP#", tempSamples[0])
            data = data.replace("#TEMP2#", tempSamples[1])
            data = data.replace("#TEMP3#", tempSamples[2])
            data = data.replace("#LOOPTIME#", str(time.time() - start_time))
            data = data.replace("#MAC#", mac)
            data = data.replace("#BATT1#", batt)
            data = data.replace("#UPDATE1#", str(delta).split('.')[0])
            data = data.replace("#ID1#",id1.rstrip())
            data = data.replace("#ID2#",id2.rstrip())
            data = data.replace("#ID3#",id3.rstrip())
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
