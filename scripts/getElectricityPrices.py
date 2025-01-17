import requests
from datetime import datetime, timedelta
import os

def get_electricity_prices():
    now = datetime.now()
    tomorrow = now + timedelta(days=1)
    year = tomorrow.strftime("%Y")
    month = tomorrow.strftime("%m")
    day = tomorrow.strftime("%d")

    url = f"https://www.elprisetjustnu.se/api/v1/prices/{year}/{month}-{day}_SE3.json"
    try:
        response = requests.get(url)
        response.raise_for_status()  # Check if the request was successful
        data = response.json()  # Parse the JSON data
        
        prices = [item['SEK_per_kWh'] for item in data if 'SEK_per_kWh' in item]
        prices_string = '\n'.join(map(str, prices))  # Join the list into a single string with each item on a new line

        file_name = f"prices_{year}_{month}_{day}.txt"
        if not os.path.exists(file_name):
            with open(file_name, 'w') as file:
                file.write(prices_string)

    except requests.exceptions.RequestException as e:
        print(f"Error fetching data: {e}")

def main():
    get_electricity_prices()

if __name__ == "__main__":
    main()
