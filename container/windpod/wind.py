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

def fetch_sensor_data(url):
    try:
        response = urlopen(url)
        return json.loads(response.read()).get('common_list', [])
    except Exception as e:
        logger.error(f"Error fetching sensor data: {e}")
        return []


def parse_sensor_data(data):
    wind_speed, wind_dir = None, None
    for item in data:
        values = list(islice(item.values(), 0, 4))
        if len(values) < 2:
            continue
        name, value = values[0], values[1].replace('m/s', '').strip()
        if name == "0x0B":
            wind_speed = value
        elif name == "0x0A":
            wind_dir = value
    return wind_speed, wind_dir


def update_template_file(template_file, output_file, wind_speed, wind_dir, ids):
    try:
        with open(template_file, 'r') as file:
            template = file.read()

        updated_data = template.replace("#SPEED1#", wind_speed or "0").replace("#DIR1#", wind_dir or "0")
        updated_data = updated_data.replace(f"#ID14#", "1c:69:7a:02:8c:4c:14")
        updated_data = updated_data.replace(f"#ID15#", "1c:69:7a:02:8c:4c:15")
        
        with open(output_file, 'w') as file:
            file.write(updated_data)

        logger.debug("Template file updated successfully.")
    except Exception as e:
        logger.error(f"Error updating template file: {e}")


def main():
    poll_count = 0
    error_count = 0
    success_count = 0

    while True:
        poll_count += 1
        logger.debug(f"Polling iteration {poll_count} started.")

        start_time = time.time()
        sensor_data = fetch_sensor_data(URL)

        wind_speed, wind_dir = parse_sensor_data(sensor_data)
        logger.debug(f"Wind Speed: {wind_speed}, Wind Direction: {wind_dir}")

        update_template_file(INPUT_FILE, OUTPUT_FILE, wind_speed, wind_dir, IDS)

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
