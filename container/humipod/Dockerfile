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
COPY eds.pub /home/sshuser/.ssh/authorized_keys
# change ownership of the key file. 
RUN chown sshuser:sshgroup /home/sshuser/.ssh/authorized_keys && chmod 600 /home/sshuser/.ssh/authorized_keys
# Start SSH service
RUN service ssh start
# Expose docker port 22
EXPOSE 22
CMD ["/usr/sbin/sshd","-D"]

COPY cron /etc/cron.d/cron
RUN chmod 0644 /etc/cron.d/cron
RUN crontab /etc/cron.d/cron
RUN touch /var/log/cron.log
#CMD cron && tail -f /var/log/cron.log

COPY humi.py humi.py
COPY start.sh start.sh
RUN chmod +x start.sh
COPY tmpl_details.xml tmpl_details.xml
COPY liveness.sh liveness.sh 
RUN chmod +x liveness.sh
RUN ls -l

RUN rm -f /var/www/html/index.html

#COPY container/liveness.sh .
#RUN chmod +x liveness.sh

RUN mkdir /mnt/ramdisk
RUN cd /var/www/html;ln -s /mnt/ramdisk/details.xml details.xml

#WORKDIR homedata/edssensors
#RUN chmod +x start.sh
ENTRYPOINT ["/bin/bash","./start.sh"]
