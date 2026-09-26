FROM ubuntu:20.04

LABEL org.containers.image.title="EDS electricity price manager" \
      org.containers.image.description="Fetches the current electricity spot price (elprisetjustnu.se), keeps a CSV history and serves the current value as details.xml" \
      org.containers.image.authors="Magnus"

ENV TZ=Europe/Stockholm
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# No mysql here: this pod stores to CSV/XML, not the DB.
RUN apt -y update && apt-get -y install \
python3 \
apache2 \
ca-certificates \
iputils-ping \
vim \
cron \
curl \
iproute2 \
jq \
openssh-server

#SSH (same pattern as the other pods)
RUN groupadd sshgroup && useradd -ms /bin/bash -g sshgroup sshuser
RUN mkdir -p /home/sshuser/.ssh
COPY eds.pub /home/sshuser/.ssh/authorized_keys
RUN chown sshuser:sshgroup /home/sshuser/.ssh/authorized_keys && chmod 600 /home/sshuser/.ssh/authorized_keys
RUN service ssh start
EXPOSE 22

COPY cron_elprice /etc/cron.d/cron_elprice
RUN chmod 0644 /etc/cron.d/cron_elprice
RUN crontab /etc/cron.d/cron_elprice
RUN touch /var/log/cron.log

COPY elprice.py elprice.py
COPY start.sh start.sh
RUN chmod +x start.sh
COPY tmpl_details.xml tmpl_details.xml
COPY elpriceLiveness.sh elpriceLiveness.sh
RUN chmod +x elpriceLiveness.sh
RUN ls -l

RUN rm -f /var/www/html/index.html

# details.xml (and the electricityprice.csv history) live together in /mnt/ramdisk,
# and both are exposed via apache: details.xml for the eds collector, and the CSV
# so the forward-looking price curve is reachable over HTTP.
RUN mkdir /mnt/ramdisk
RUN cd /var/www/html; ln -s /mnt/ramdisk/details.xml details.xml; ln -s /mnt/ramdisk/electricityprice.csv electricityprice.csv

ENTRYPOINT ["/bin/bash","./start.sh"]
