import serial
from datetime import datetime

ser = serial.Serial(
        port='/dev/ttyUSB0',
        baudrate=9600,
        parity=serial.PARITY_NONE,
        stopbits=serial.STOPBITS_ONE,
        bytesize=serial.EIGHTBITS
)
encoding = 'utf-8'
i=1
while i:
    readedText = ser.readline()
    print(readedText.decode(encoding))
    now = datetime.now()
    timestamp = now.strftime("%Y-%m-%d %H:%M:%S")
    f = open("www/details.txt","w")
    l = open("www/log.txt","a")
    try:
        ln = timestamp +" " + readedText.decode(encoding)
        f.write(ln)
    except Exception as e:
        st = timestamp + " " + str(e) + "\n"
        l.write(st)
        l.close()
    f.close()
    i=0
ser.close()
