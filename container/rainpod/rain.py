import os
import sys
import time
import json
import logging
import urllib.error
from urllib.request import urlopen

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
INPUT_FILE = 'tmpl_details.xml'
OUTPUT_FILE = '/mnt/ramdisk/details.xml'

# Sensor keys
RAIN_EVENT_KEY = "0x0D"
RAIN_WEEK_KEY  = "0x11"
RAIN_MONTH_KEY = "0x12"

# Device MAC addresses
MAC_ID16 = "1c:69:7a:02:8c:4c:16"
MAC_ID17 = "1c:69:7a:02:8c:4c:17"
MAC_ID18 = "1c:69:7a:02:8c:4c:18"


def validate_environment():
    if not os.path.isfile(INPUT_FILE):
        logger.error(f"Template file not found: {INPUT_FILE}")
        sys.exit(1)
    out_dir = os.path.dirname(OUTPUT_FILE) or '.'
    if not os.access(out_dir, os.W_OK):
        logger.error(f"Output directory not writable: {out_dir}")
        sys.exit(1)


def fetch_sensor_data(url):
    for attempt in range(2):
        try:
            response = urlopen(url, timeout=10)
            return json.loads(response.read()).get('rain', [])
        except (urllib.error.URLError, json.JSONDecodeError, OSError) as e:
            if attempt == 0:
                logger.warning(f"Fetch attempt 1 failed: {e}. Retrying in 5s...")
                time.sleep(5)
            else:
                logger.error(f"Fetch attempt 2 failed: {e}. Skipping poll cycle.")
    return []


def parse_rain_value(data, key):
    for item in data:
        if item.get("id") == key:
            return item.get("val", "").replace("mm", "").strip()
    return None


def update_template_file(template_file, output_file, rain, rain_week, rain_month):
    try:
        with open(template_file, 'r') as f:
            template = f.read()

        updated = (template
                   .replace("#RAIN1#",  rain       or "0")
                   .replace("#ID16#",   MAC_ID16)
                   .replace("#RAINW1#", rain_week  or "0")
                   .replace("#ID17#",   MAC_ID17)
                   .replace("#RAINM1#", rain_month or "0")
                   .replace("#ID18#",   MAC_ID18))

        tmp_path = output_file + ".tmp"
        with open(tmp_path, 'w') as f:
            f.write(updated)
        os.replace(tmp_path, output_file)

        logger.debug("Template file updated successfully.")
    except OSError as e:
        logger.error(f"Error updating template file: {e}")


def main():
    validate_environment()

    while True:
        start_time = time.time()
        sensor_data = fetch_sensor_data(URL)

        rain = parse_rain_value(sensor_data, RAIN_EVENT_KEY)
        if rain is None:
            logger.warning(f"Value for {RAIN_EVENT_KEY} not found in sensor data")

        rain_week = parse_rain_value(sensor_data, RAIN_WEEK_KEY)
        if rain_week is None:
            logger.warning(f"Value for {RAIN_WEEK_KEY} not found in sensor data")

        rain_month = parse_rain_value(sensor_data, RAIN_MONTH_KEY)
        if rain_month is None:
            logger.warning(f"Value for {RAIN_MONTH_KEY} not found in sensor data")

        logger.debug(f"Rain event={rain} week={rain_week} month={rain_month}")
        update_template_file(INPUT_FILE, OUTPUT_FILE, rain, rain_week, rain_month)

        sleep_time = max(0, POLL_INTERVAL - (time.time() - start_time))
        time.sleep(sleep_time)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        logger.info("Process interrupted by user.")
    except Exception as e:
        logger.error(f"Unexpected error: {e}")
