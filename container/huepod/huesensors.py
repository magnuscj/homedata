import subprocess
import time
from time import sleep  # Import sleep function
import json
import logging
import requests
from datetime import datetime, timedelta
from urllib.request import urlopen
from itertools import islice
from tabulate import tabulate
import os  # Import os module

PATH = '/container/huepod/'
TEMPLATE_ITEM_FILE = 'detail.xml'
TEMPLATE_FILE = 'detailes.xml'
OUTPUT_FILE = '/mnt/ramdisk/detailes.xml'

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

def retreive_data():
    
    data = []

    presence = []
    pres_sens_no = []
    pres_batteries = []
    pres_names = []

    temperatures = []
    temp_sens_no = []
    temp_batteries = []

    lights = []
    light_sens_no = []
    light_batteries = []

    uid = []
    uids = []
 

    response = requests.get("http://192.168.50.151/api/HTymPjBT0g1JdTwXFdYe-N26G9IQ8MDQ8quVIkr1/")
    response.raise_for_status()  # Check if the request was successful
    data = response.json()  # Parse the JSON data

    detailes = get_details_template(os.getcwd()+PATH + TEMPLATE_FILE)
    for key, value in data.items():
        if key == 'sensors':               
            for sensor_id, sensor_data in value.items():
                if sensor_data['type'] == 'ZLLTemperature':
                    temp_sens_no.append(sensor_id)
                    detail = get_detail_template(os.getcwd()+PATH + TEMPLATE_ITEM_FILE)
                    detail = detail.replace("#DESCRIPTION#", sensor_data['type'])
                    for sen_key, sen_value in sensor_data.items():
                        if sen_key == 'state':
                            detail = detail.replace("#DATE#", sen_value['lastupdated'])
                            for val_key, val in sen_value.items():
                                if val_key == 'temperature':
                                    temperatures.append(val)
                                    detail = detail.replace("#DATA#", str(val))
                        if sen_key == 'config':
                            temp_batteries.append(sen_value['battery'])
                            detail = detail.replace("#BATT#", str(sen_value['battery']))
                        if sen_key == 'uniqueid':
                            uid.append(sen_value)
                            detail = detail.replace("#ID#", str(sen_value))
                    detailes = detailes.replace("</Devices-Detail-Response>", detail)
                    detailes = detailes + "\n</Devices-Detail-Response>"
                    detail = ''

                if sensor_data['type'] == 'ZLLLightLevel':
                    light_sens_no.append(sensor_id)
                    detail = get_detail_template(os.getcwd()+PATH + TEMPLATE_ITEM_FILE)
                    detail = detail.replace("#DESCRIPTION#", sensor_data['type'])
                    for sen_key, sen_value in sensor_data.items():
                        if sen_key == 'state':
                            detail = detail.replace("#DATE#", sen_value['lastupdated'])
                            for val_key, val in sen_value.items():
                                if val_key == 'lightlevel':
                                    lights.append(val)
                                    detail = detail.replace("#DATA#", str(val))
                        if sen_key == 'config':
                            light_batteries.append(sen_value['battery'])
                            detail = detail.replace("#BATT#", str(sen_value['battery']))
                        if sen_key == 'uniqueid':
                            uid.append(sen_value)
                            detail = detail.replace("#ID#", str(sen_value))

                    detailes = detailes.replace("</Devices-Detail-Response>", detail)
                    detailes = detailes + "\n</Devices-Detail-Response>"
                    detail = ''

                if sensor_data['type'] == 'ZLLPresence':
                    pres_sens_no.append(sensor_id)
                    detail = get_detail_template(os.getcwd()+PATH + TEMPLATE_ITEM_FILE)
                    detail = detail.replace("#DESCRIPTION#", sensor_data['type'])
                    for sen_key, sen_value in sensor_data.items():
                        if sen_key == 'state':
                            detail = detail.replace("#DATE#", sen_value['lastupdated'])
                            for val_key, val in sen_value.items():
                                if val_key == 'presence':
                                    presence.append(val)
                                    detail = detail.replace("#DATA#", str(val))
                        if sen_key == 'config':
                            pres_batteries.append(sen_value['battery'])
                            detail = detail.replace("#BATT#", str(sen_value['battery']))
                        if sen_key == 'name':
                            pres_names.append(sen_value )
                            detail = detail.replace("#NAME#", str(sen_value))
                        if sen_key == 'uniqueid':
                            uid.append(sen_value)
                            detail = detail.replace("#ID#", str(sen_value))
                    
                    detailes = detailes.replace("</Devices-Detail-Response>", detail)
                    detailes = detailes + "\n</Devices-Detail-Response>"
                    detail = ''

                if len(uid) == 3:
                    uids.append(uid)
                    uid = []

    # Print temperature and battery table
    temp_table = zip(temp_sens_no, [float(temp)/100 for temp in temperatures], lights, presence, temp_batteries, pres_names)
    # Clear the screen
    os.system('cls' if os.name == 'nt' else 'clear')
    print(tabulate(temp_table, headers=["ID", "Temp (C)", "Light", "Presence" ,"Battery (%)", "Name"], tablefmt="fancy_grid"))
    

def main():
    poll_count = 0
    error_count = 0
    success_count = 0
    retreive_data()
    create_detail(os.getcwd()+PATH + TEMPLATE_ITEM_FILE)
    
def create_detail(template_file):
    try:
        with open(template_file, 'r') as file:
            template = file.read()
            file.close()
    except Exception as e:
        print("Current Directory:", os.getcwd())
        logger.error(f"Error reading template file: {e}")
        return

def get_detail_template(template_file):
    try:
        with open(template_file, 'r') as file:
            template = file.read()
            file.close()
    except Exception as e:
        print("Current Directory:", os.getcwd())
        logger.error(f"Error reading template file: {e}")
    return template

def get_details_template(template_file):
    try:
        with open(template_file, 'r') as file:
            template = file.read()
            file.close()
    except Exception as e:
        print("Current Directory:", os.getcwd())
        logger.error(f"Error reading template file: {e}")
    return template

if __name__ == "__main__":
    try:
        main()

    except KeyboardInterrupt:
        logger.info("Process interrupted by user.")
    except Exception as e:
        logger.error(f"Unexpected error: {e}")