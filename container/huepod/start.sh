#!/bin/bash
service ssh start
service ssh status
service apache2 start
service cron start
python3 humi.py

