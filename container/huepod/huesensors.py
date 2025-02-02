import subprocess
import time
from time import sleep  # Import sleep function
import json
import logging
import requests
from datetime import datetime, timedelta
from urllib.request import urlopen
from itertools import islice
import os  # Import os module

PATH = '' #'/container/huepod/'
TEMPLATE_ITEM_FILE = 'detail.xml'
TEMPLATE_FILE = 'detailes.xml'
OUTPUT_FILE = '/mnt/ramdisk/detailes.xml'
URL = 'http://192.168.50.151/api/HTymPjBT0g1JdTwXFdYe-N26G9IQ8MDQ8quVIkr1/'

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

now = datetime.now()
timestamp = now.strftime("%Y-%m-%d %H:%M:%S")
start_time = time.time()

def create_details():
    
    try:
        response = requests.get(URL)
        response.raise_for_status()
        data = response.json()
    except requests.RequestException as e:
        logger.error(f"Error fetching data: {e}")
        return

    details = get_template(os.path.join(os.getcwd() + PATH + TEMPLATE_FILE))
    detail_t  = get_template(os.path.join(os.getcwd() + PATH + TEMPLATE_ITEM_FILE))

    for key, value in data.items():
        if key == 'sensors':
            for sensor_id, sensor_data in value.items():
                if sensor_data['type'] in ['ZLLTemperature', 'ZLLLightLevel', 'ZLLPresence']:
                    detail = detail_t
                    detail = detail.replace("#DESCRIPTION#", sensor_data['type'])
                    for sen_key, sen_value in sensor_data.items():
                        if sen_key == 'state':
                            detail = detail.replace("#DATE#", sen_value['lastupdated'])
                            for val_key, val in sen_value.items():
                                if val_key in ['temperature', 'lightlevel', 'presence']:
                                    detail = detail.replace("#DATA#", str(val))
                        if sen_key == 'config':
                            detail = detail.replace("#BATT#", str(sen_value['battery']))
                        if sen_key == 'uniqueid':
                            detail = detail.replace("#ID#", str(sen_value))
                        if sen_key == 'name' and sensor_data['type'] == 'ZLLPresence':
                            detail = detail.replace("#NAME#", str(sen_value))
                    details = details.replace("</Devices-Detail-Response>", detail)
                    details += "\n</Devices-Detail-Response>"
    return  details

def main():
    while 1:
        details = create_details()
        print(details)
        sleep(60)

def get_template(template_file):
    try:
        with open(template_file, 'r') as file:
            template = file.read()
    except Exception as e:
        logger.error(f"Error reading template file: {e}")
    return template

if __name__ == "__main__":
    try:
        main()

    except KeyboardInterrupt:
        logger.info("Process interrupted by user.")
    except Exception as e:
        logger.error(f"Unexpected error: {e}")