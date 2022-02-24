import serial
import subprocess
from datetime import datetime

ser = serial.Serial(
        port='/dev/ttyUSB0',
        baudrate=38400,
        parity=serial.PARITY_NONE,
        stopbits=serial.STOPBITS_ONE,
        bytesize=serial.EIGHTBITS
)
encoding = 'utf-8'
i=1
macaddress = subprocess.Popen("ip addr show | grep ether | awk {'print $2'}", shell=True, stdout=subprocess.PIPE)
mac = macaddress.stdout.read()
windSamples = []
windSamplesSort = []
windSamplesMax = []
windDirSamples = []
maxWindSamples = 60*5
maxWindMaxSamples = 60*60
pollCount = 0
errors = 0
success = 0

now = datetime.now()
timestamp = now.strftime("%Y-%m-%d %H:%M:%S")

while 1:
    pollCount+=1    
    try:
        readedText = ser.readline()
        st = readedText.decode(encoding).split(",")
        windSamples.append(round(float(st[3]),2))
        windSamplesMax.append(round(float(st[3]),2))
        windDirSamples.append(int(st[1]))
        currentWind = str(round(float(st[3]),2))
        now = datetime.now()
        timestamp = now.strftime("%Y-%m-%d %H:%M:%S")

        if len(windSamplesMax) > maxWindMaxSamples:
            windSamplesMax.pop(0)
        
        if len(windSamples) > maxWindSamples:
            windSamples.pop(0)
            windDirSamples.pop(0)

        windSpeed = str(round(sum(windSamples)/len(windSamples),2))
        windDirection = str(int(sum(windDirSamples)/len(windDirSamples)))
        windSamplesSort = windSamplesMax.copy()
        windSamplesSort.sort(reverse=True)
        windMax = str(windSamplesSort[0])

        with open(r'/mnt/ramdisk/tmpl_details.xml', 'r') as file:
            data = file.read()
            file.close()
            data = data.replace("#MAC#", mac.decode(encoding))
            data = data.replace("#TIME#", timestamp)
            data = data.replace("#WIND#", windSpeed)
            data = data.replace("#WINDMAX#", windMax)
            data = data.replace("#DIRECTION#", windDirection)
            data = data.replace("#POLLCOUNT#", str(pollCount))
            data = data.replace("#ERRORS#", str(errors))

        with open(r'/mnt/ramdisk/details.xml', 'w') as file:
            file.write(data) 
            file.close()
        success+=1
        #st_debug = timestamp + " " + currentWind + " " + windSpeed + ", " + windMax + \
        #", size: " + str(len(windSamples)) + " " + windDirection
        #print(st_debug)
        #print(readedText)        
    except Exception as e:
        errors+=1
        est = timestamp + " " + str(e)
        print(est)
    i=0
ser.close()
