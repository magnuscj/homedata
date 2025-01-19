import requests
from datetime import datetime, timedelta
import os

def get_electricity_prices():
    now = datetime.now()
    if now.hour > 13:
        tomorrow = now + timedelta(days=1)
    else:
        tomorrow = now
    year = tomorrow.strftime("%Y")
    month = tomorrow.strftime("%m")
    day = tomorrow.strftime("%d")

    url = f"https://www.elprisetjustnu.se/api/v1/prices/{year}/{month}-{day}_SE3.json"
    try:
        response = requests.get(url)
        response.raise_for_status()  # Check if the request was successful
        data = response.json()  # Parse the JSON data
        
        prices = [item['SEK_per_kWh'] for item in data if 'SEK_per_kWh' in item]
        dates = [datetime.fromisoformat(item['time_start']).strftime("Y-%m-%d %H:%M:%S") for item in data if 'time_start' in item]
        hours = [datetime.fromisoformat(item['time_start']).strftime("%H") for item in data if 'time_start' in item]
        combined = [f"{time}, {price}" for time, price in zip(hours, prices)]
        combined_string = '\n'.join(combined)  # Join the list into a single string with each item on a new line

        file_name = f"prices_{year}_{month}_{day}.txt"
        if not os.path.exists(file_name):
            with open(file_name, 'w') as file:
                file.write(combined_string)

    except requests.exceptions.RequestException as e:
        print(f"Error fetching data: {e}")

def main():
    get_electricity_prices()

if __name__ == "__main__":
    main()

#'SEK_per_kWh': -0.00218
#'EUR_per_kWh': -0.00019
#'EXR': 11.49505
#'time_start': '2025-01-18T00:00:00+01:00'
#'time_end': '2025-01-18T01:00:00+01:00'}