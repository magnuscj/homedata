import subprocess
import os
import time
import json
import requests
import logging
from datetime import datetime
from urllib.request import urlopen
from itertools import islice

# Logger setup
def setup_logger():
    logger = logging.getLogger('sensor_logger')
    logger.setLevel(logging.DEBUG)

    file_handler = logging.FileHandler('sensorlog.log')
    file_handler.setLevel(logging.ERROR)

    console_handler = logging.StreamHandler()
    console_handler.setLevel(logging.DEBUG)

    formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
    file_handler.setFormatter(formatter)
    console_handler.setFormatter(formatter)

    logger.addHandler(file_handler)
    logger.addHandler(console_handler)
    return logger

logger = setup_logger()

# Configuration
POLL_INTERVAL = 60  # Seconds between polls
URL = "http://192.168.50.237/get_livedata_info?"
MAC_ADDRESS = "1c:69:7a:02:8c:humi"
IDS = [f"{MAC_ADDRESS}:{x}" for x in range(1, 14)]
#TEMPLATE_FILE = 'tmpl_Huedetails.xml'
TEMPLATE_ITEM_FILE = 'detail_t.xml'
TEMPLATE_FILE = 'detailes_t.xml'
OUTPUT_FILE = '/mnt/ramdisk/details.xml'

def fetch_sensor_data(url):
    try:
        re = requests.get(url)
        re.raise_for_status()
        data = re.json()
        return data
    except Exception as e:
        logger.error(f"Error fetching sensor data: {e}")
        return []

def update_template_file(template_item, template_file, output_file, data):
    try:
        with open(template_item, 'r') as file:
            detail_t = file.read()
        with open(template_file, 'r') as file:
            details = file.read()

        for key, value in data.items():
            if key == "ch_soil":
                    for val in value:
                        try:
                            detail = detail_t.replace("#DATA#", val["humidity"].replace("%", ""))
                            detail = detail.replace("#NAME#", val["name"])
                            detail = detail.replace("#BATT#", val["voltage"])
                            detail = detail.replace("#ID#", MAC_ADDRESS + val["channel"])
                            detail = detail.replace("#DATE#", datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
                            details = details.replace("</Devices-Detail-Response>", detail)
                            details += "\n</Devices-Detail-Response>"
                        except Exception as e:
                            logger.error(e)

        with open(output_file, 'w') as file:
            file.write(details)

        logger.debug("Template file updated successfully.")
    except Exception as e:
        logger.error(f"Error updating template file: {e}")
        with open('detailes.xml', 'w') as file:
            file.write(details)


def main():
    poll_count = 0
    error_count = 0
    success_count = 0

    while True:
        poll_count += 1
        logger.debug(f"Polling iteration {poll_count} started.")

        start_time = time.time()
        sensor_data = fetch_sensor_data(URL)

        update_template_file(TEMPLATE_ITEM_FILE, TEMPLATE_FILE, OUTPUT_FILE, sensor_data)

        success_count += 1
        elapsed_time = time.time() - start_time
        sleep_time = max(0, POLL_INTERVAL - elapsed_time)
        logger.debug(f"Polling iteration {poll_count} completed. Sleeping for {sleep_time:.2f} seconds.")
        time.sleep(sleep_time)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        logger.info("Process interrupted by user.")
    except Exception as e:
        logger.error(f"Unexpected error: {e}")
