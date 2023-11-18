FROM ubuntu:20.04

LABEL org.containers.image.title="EDS data manager" \
      org.containers.image.description="Collects, stores and vizualise data from Embedded data systems" \
      org.containers.image.authors="Magnus"

ENV TZ=Europe/Stockholm
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt -y update && apt-get -y install \
g++ \
git \
make \
libcurl4-gnutls-dev \
mysql-server \
libmysqlclient-dev \
iputils-ping \
php7.4 \
php7.4-cli \
php7.4-common \
php7.4-mysql \
php7.4-mbstring \
php7.4-gd \
vim \
cron \
curl \
iproute2 \
jq \
openssh-server

#SSH
#RUN apt update && apt install  openssh-server sudo -y
# Create a user “sshuser” and group “sshgroup”
RUN groupadd sshgroup && useradd -ms /bin/bash -g sshgroup sshuser
# Create sshuser directory in home
RUN mkdir -p /home/sshuser/.ssh
# Copy the ssh public key in the authorized_keys file. The idkey.pub below is a public key file you get from ssh-keygen. They are under ~/.ssh directory by default.
COPY container/eds.pub /home/sshuser/.ssh/authorized_keys
# change ownership of the key file. 
RUN chown sshuser:sshgroup /home/sshuser/.ssh/authorized_keys && chmod 600 /home/sshuser/.ssh/authorized_keys
# Start SSH service
RUN service ssh start
# Expose docker port 22
EXPOSE 22
CMD ["/usr/sbin/sshd","-D"]

COPY container/cron_eds /etc/cron.d/cron_eds
RUN chmod 0644 /etc/cron.d/cron_eds
RUN crontab /etc/cron.d/cron_eds  #Remove?
RUN touch /var/log/cron.log
#CMD cron && tail -f /var/log/cron.log

COPY container/php.ini /etc/php/7.4/cli/php.ini
RUN mkdir -p /var/www/html/picture

## Font workaround
RUN mkdir -p /usr/share/fonts/truetype/msttcorefonts
COPY container/arial.ttf /usr/share/fonts/truetype/msttcorefonts
COPY container/arialbd.ttf /usr/share/fonts/truetype/msttcorefonts
COPY container/verdana.ttf /usr/share/fonts/truetype/msttcorefonts
COPY container/verdanab.ttf /usr/share/fonts/truetype/msttcorefonts

RUN git clone https://github.com/leethomason/tinyxml2.git
ARG CACHE_DATE=xx
RUN git clone https://github.com/magnuscj/homedata.git

COPY visualize/*.html /var/www/html

COPY container/start.sh homedata/edssensors
RUN chmod +x homedata/edssensors/start.sh

COPY container/backup.sh homedata/edssensors
RUN chmod +x homedata/edssensors/backup.sh
RUN mkdir /usr/storage
RUN touch /usr/storage/txt.txt

COPY container/createSensorConfig.sh homedata/edssensors
RUN chmod +x homedata/edssensors/createSensorConfig.sh

COPY container/restore.sh homedata/edssensors
RUN chmod +x homedata/edssensors/restore.sh

COPY container/jpgraph-4.3.5.tar.gz .
RUN  tar -xf  jpgraph-4.3.5.tar.gz

RUN mkdir /mnt/ramdisk
RUN cd /var/www/html;ln -s /mnt/ramdisk/details.xml details.xml

RUN cd homedata/edssensors; make 
COPY ../visualize/*.html /var/www/html/

ARG CACHE_DATE=
WORKDIR homedata/edssensors
RUN chmod +x start.sh
ENTRYPOINT ["/bin/bash","./start.sh"]
