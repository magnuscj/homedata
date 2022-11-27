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
cron

COPY cron_eds /etc/cron.d/cron_eds
RUN chmod 0644 /etc/cron.d/cron_eds
RUN crontab /etc/cron.d/cron_eds  #Remove?
RUN touch /var/log/cron.log
#CMD cron && tail -f /var/log/cron.log

COPY php.ini /etc/php/7.4/cli/php.ini
RUN mkdir -p /var/www/html/picture

## Font workaround
RUN mkdir -p /usr/share/fonts/truetype/msttcorefonts
COPY arial.ttf /usr/share/fonts/truetype/msttcorefonts
COPY arialbd.ttf /usr/share/fonts/truetype/msttcorefonts
COPY verdana.ttf /usr/share/fonts/truetype/msttcorefonts
COPY verdanab.ttf /usr/share/fonts/truetype/msttcorefonts

RUN git clone https://github.com/leethomason/tinyxml2.git
ARG CACHE_DATE=xx
RUN git clone https://github.com/magnuscj/homedata.git

COPY start.sh homedata/edssensors
RUN chmod +x homedata/edssensors/start.sh

COPY backup.sh homedata/edssensors
RUN chmod +x homedata/edssensors/backup.sh

COPY createSensorConfig.sh homedata/edssensors
RUN chmod +x homedata/edssensors/createSensorConfig.sh

COPY restore.sh homedata/edssensors
RUN chmod +x homedata/edssensors/restore.sh

COPY jpgraph-4.3.5.tar.gz .
RUN  tar -xf  jpgraph-4.3.5.tar.gz

RUN cd homedata/edssensors; make 
WORKDIR homedata/edssensors
ARG CACHE_DATE=
RUN git log
ENTRYPOINT ["./start.sh"]

