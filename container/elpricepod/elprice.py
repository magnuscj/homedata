import os
import sys
import csv
import time
import json
import logging
import urllib.error
from datetime import datetime, timezone
from urllib.request import urlopen, Request

# Logger setup (mirrors the rain/wind pod pattern)
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
POLL_INTERVAL = 300  # Seconds between polls (5 min; prices change per 15-min slot)
PRICE_AREA = os.environ.get("PRICE_AREA", "SE3")
# elprisetjustnu.se public API. {Y}/{m}-{d}_{area}.json
URL_TEMPLATE = "https://www.elprisetjustnu.se/api/v1/prices/{year}/{month}-{day}_{area}.json"

INPUT_FILE = 'tmpl_details.xml'
# details.xml is served by apache from /mnt/ramdisk (symlinked into /var/www/html).
# The CSV history lives in the SAME directory as details.xml, per requirement.
OUTPUT_DIR = os.environ.get("OUTPUT_DIR", "/mnt/ramdisk")
OUTPUT_FILE = os.path.join(OUTPUT_DIR, "details.xml")
CSV_FILE = os.path.join(OUTPUT_DIR, "electricityprice.csv")

# A stable, human-readable ROM id for the price "device". The eds collector
# hashes ROMId+metric+nodeType into the canonical FNV sensorid if it ever polls
# this pod, so this only needs to be stable, not meaningful.
ROM_ID = "ELPRICE:SE3"

# The public API rejects the default python-urllib User-Agent (HTTP 403),
# so send a conventional UA like a browser/requests client would.
HTTP_HEADERS = {
    "User-Agent": "homedata-elprice/1.0 (+https://github.com/magnuscj/homedata)"
}


def validate_environment():
    if not os.path.isfile(INPUT_FILE):
        logger.error(f"Template file not found: {INPUT_FILE}")
        sys.exit(1)
    if not os.path.isdir(OUTPUT_DIR):
        try:
            os.makedirs(OUTPUT_DIR, exist_ok=True)
        except OSError as e:
            logger.error(f"Cannot create output directory {OUTPUT_DIR}: {e}")
            sys.exit(1)
    if not os.access(OUTPUT_DIR, os.W_OK):
        logger.error(f"Output directory not writable: {OUTPUT_DIR}")
        sys.exit(1)


def build_url(now):
    return URL_TEMPLATE.format(
        year=now.strftime("%Y"),
        month=now.strftime("%m"),
        day=now.strftime("%d"),
        area=PRICE_AREA,
    )


def build_url_for(day):
    return URL_TEMPLATE.format(
        year=day.strftime("%Y"),
        month=day.strftime("%m"),
        day=day.strftime("%d"),
        area=PRICE_AREA,
    )


def fetch_prices(url):
    for attempt in range(2):
        try:
            request = Request(url, headers=HTTP_HEADERS)
            response = urlopen(request, timeout=10)
            return json.loads(response.read())
        except (urllib.error.URLError, json.JSONDecodeError, OSError) as e:
            if attempt == 0:
                logger.warning(f"Fetch attempt 1 failed: {e}. Retrying in 5s...")
                time.sleep(5)
            else:
                logger.error(f"Fetch attempt 2 failed: {e}. Skipping poll cycle.")
    return []


def fetch_forecast(now):
    """Fetch the full forward-looking price curve.

    Mirrors eds/getElectricityPrices.py: always fetch today's whole curve, and
    once the Nordic day-ahead prices for tomorrow are published (after ~13:00),
    also fetch tomorrow so future hours are captured in advance.
    Returns the combined list of interval dicts.
    """
    from datetime import timedelta

    prices = fetch_prices(build_url_for(now))
    if now.hour >= 13:
        tomorrow = now + timedelta(days=1)
        tomorrow_prices = fetch_prices(build_url_for(tomorrow))
        if tomorrow_prices:
            logger.debug(f"Fetched {len(tomorrow_prices)} tomorrow intervals in advance.")
            prices = list(prices) + list(tomorrow_prices)
    return prices


def select_current_price(prices, now):
    """Return (price, time_start_iso) for the interval that contains 'now',
    or None if no interval matches. 'now' must be timezone-aware."""
    for item in prices:
        try:
            start = datetime.fromisoformat(item["time_start"])
            end = datetime.fromisoformat(item["time_end"])
        except (KeyError, ValueError):
            continue
        if start <= now < end:
            return item.get("SEK_per_kWh"), item["time_start"]
    return None


def upsert_csv(csv_file, prices):
    """Merge the fetched intervals (today + any future) into the CSV, keyed by
    interval_start. New future intervals are added; existing rows are kept.
    This captures the forward-looking curve, not just the current hour."""
    header = ["interval_start", "sek_per_kwh", "recorded_at"]
    existing = {}
    if os.path.isfile(csv_file):
        try:
            with open(csv_file, 'r', newline='') as f:
                reader = csv.reader(f)
                rows = list(reader)
            for row in rows[1:]:
                if len(row) >= 2:
                    existing[row[0]] = row  # keep full row (preserve first-seen recorded_at)
        except OSError as e:
            logger.warning(f"Could not read existing CSV: {e}")

    recorded_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    added = 0
    for item in prices:
        start_iso = item.get("time_start")
        price = item.get("SEK_per_kWh")
        if start_iso is None or price is None:
            continue
        if start_iso not in existing:
            existing[start_iso] = [start_iso, str(price), recorded_at]
            added += 1

    if added == 0:
        logger.debug("No new intervals; CSV unchanged.")
        return

    # Rewrite the CSV sorted by interval_start (ISO 8601 sorts chronologically).
    try:
        ordered = sorted(existing.values(), key=lambda r: r[0])
        tmp_path = csv_file + ".tmp"
        with open(tmp_path, 'w', newline='') as f:
            writer = csv.writer(f)
            writer.writerow(header)
            writer.writerows(ordered)
        os.replace(tmp_path, csv_file)
        logger.debug(f"CSV upserted: {added} new interval(s), {len(ordered)} total.")
    except OSError as e:
        logger.error(f"Error writing CSV: {e}")


def update_template_file(template_file, output_file, price, interval_start_iso, poll_count, loop_time):
    """Write details.xml containing ONLY the current valid price (no series)."""
    try:
        with open(template_file, 'r') as f:
            template = f.read()

        updated = (template
                   .replace("#PRICE#", "" if price is None else str(price))
                   .replace("#ROMID#", ROM_ID)
                   .replace("#TIME#", interval_start_iso or datetime.now().isoformat())
                   .replace("#POLLCOUNT#", str(poll_count))
                   .replace("#LOOPTIME#", f"{loop_time:.3f}"))

        tmp_path = output_file + ".tmp"
        with open(tmp_path, 'w') as f:
            f.write(updated)
        os.replace(tmp_path, output_file)

        logger.debug("details.xml updated successfully.")
    except OSError as e:
        logger.error(f"Error updating template file: {e}")


def main():
    validate_environment()
    poll_count = 0

    while True:
        poll_count += 1
        start_time = time.time()

        now = datetime.now(timezone.utc).astimezone()  # tz-aware local time

        # Fetch the full forward-looking curve (today + tomorrow after ~13:00)
        # and merge every interval into the CSV so future hours are stored in
        # advance, exactly like eds pre-loads tomorrow's day-ahead prices.
        prices = fetch_forecast(now)
        upsert_csv(CSV_FILE, prices)

        # details.xml still holds ONLY the single currently-valid price.
        selected = select_current_price(prices, now)
        if selected is None:
            logger.warning("No price interval matches the current time; skipping details.xml update.")
        else:
            price, interval_start_iso = selected
            logger.debug(f"Current price {price} SEK/kWh (interval {interval_start_iso})")
            update_template_file(INPUT_FILE, OUTPUT_FILE, price, interval_start_iso,
                                 poll_count, time.time() - start_time)

        sleep_time = max(0, POLL_INTERVAL - (time.time() - start_time))
        time.sleep(sleep_time)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        logger.info("Process interrupted by user.")
    except Exception as e:
        logger.error(f"Unexpected error: {e}")
