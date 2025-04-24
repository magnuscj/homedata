import subprocess
import time
import json
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
MAC_ADDRESS = "1c:69:7a:02:8c:4c"
IDS = [f"{MAC_ADDRESS}:{x}" for x in range(1, 14)]
#TEMPLATE_FILE = 'tmpl_Huedetails.xml'
INPUT_FILE = 'tmpl_details.xml'
OUTPUT_FILE = '/mnt/ramdisk/details.xml'
mac = "1c:69:7a:02:8c:4c"
ids = []

for x in range(1,14):
        ids.append(mac.strip()+":"+str(x))

logger.debug(ids)

def fetch_sensor_data(url):
    try:
        response = urlopen(url)
        return json.loads(response.read()).get('ch_soil', [])
    except Exception as e:
        logger.error(f"Error fetching sensor data: {e}")
        return []


def parse_sensor_data(data):
    humidity = []
    for item in data:
        values = list(islice(item.values(), 0, 5))
        if len(values) < 2:
            continue
        humidity.append(values[4].replace('%', '').strip())
    return humidity 


def update_template_file(template_file, output_file, humi, ids):
    try:
        with open(template_file, 'r') as file:
            template = file.read()

        for i in range(len(humi)):
            try:
                template = template.replace("#HUMI"+str(i)+"#", humi[i])
                template = template.replace("#ID"+str(i)+"#", ids[i])
                print(ids[i])
            except Exception as e:
                logger.error(e)

        with open(output_file, 'w') as file:
            file.write(template)

        logger.debug("Template file updated successfully.")
    except Exception as e:
        logger.error(f"Error updating template file: {e}")
        with open('detailes.xml', 'w') as file:
            file.write(template)


def main():
    poll_count = 0
    error_count = 0
    success_count = 0

    while True:
        poll_count += 1
        logger.debug(f"Polling iteration {poll_count} started.")

        start_time = time.time()
        sensor_data = fetch_sensor_data(URL)

        humidity = parse_sensor_data(sensor_data)
        logger.debug(f"Humidity: {humidity}")

        update_template_file(INPUT_FILE, OUTPUT_FILE, humidity, IDS)

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
