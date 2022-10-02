import subprocess
import time
from datetime import datetime, timedelta

encoding = 'utf-8'
i=1
tempSamples = []
sensorTimeSamples = []
battSamples = []
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

strx1 ='curl -sX GET http://192.168.1.151/api/HTymPjBT0g1JdTwXFdYe-N26G9IQ8MDQ8quVIkr1/sensors | jq .\\"'
strx2 =['14','33','36']
strx3 ='\\".state.temperature'
sLastTime ='\\".state.lastupdated'
sBatt ='\\".config.battery'


while 1:
    pollCount+=1    
    start_time = time.time()
    del tempSamples[:]
    for n in strx2:
        strx = strx1 + n + strx3
        temperatur = subprocess.Popen(strx, shell=True, stdout=subprocess.PIPE)
        temp = str(float(temperatur.stdout.read())/100)
        tempSamples.append(temp)

        strx = strx1 + n + sLastTime
        sensorTime = subprocess.Popen(strx, shell=True, stdout=subprocess.PIPE)
        now = datetime.now()
        timestamp = now.strftime("%Y-%m-%d %H:%M:%S")
        sTime = sensorTime.stdout.read().decode("utf-8").replace('"\n','').replace('"','')
        delta = now - datetime.strptime(sTime, "%Y-%m-%dT%H:%M:%S") - timedelta(hours=2)
        sensorTimeSamples.append(delta)

        strx = strx1 + n + sBatt
        battery = subprocess.Popen(strx, shell=True, stdout=subprocess.PIPE)
        batt = str(float(battery.stdout.read()))
        battSamples.append(batt)

    try:
        with open(r'tmpl_Huedetails.xml', 'r') as file:
            data = file.read()
            file.close()
            data = data.replace("#TIME#", timestamp)
            data = data.replace("#TEMP#", tempSamples[0])
            data = data.replace("#TEMP2#", tempSamples[1])
            data = data.replace("#TEMP3#", tempSamples[2])
            data = data.replace("#LOOPTIME#", str(time.time() - start_time))
            data = data.replace("#MAC#", mac)
            data = data.replace("#BATT1#", battSamples[0])
            data = data.replace("#BATT2#", battSamples[1])
            data = data.replace("#BATT3#", battSamples[2])
            data = data.replace("#UPDATE1#", str(sensorTimeSamples[0]).split('.')[0])
            data = data.replace("#UPDATE2#", str(sensorTimeSamples[1]).split('.')[0])
            data = data.replace("#UPDATE3#", str(sensorTimeSamples[2]).split('.')[0])
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
