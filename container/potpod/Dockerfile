FROM ubuntu:20.04

LABEL org.containers.image.title="Pot humidity monitor" \
      org.containers.image.description="Monitors the humidity" \
      org.containers.image.authors="Magnus"

ENV TZ=Europe/Stockholm
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt -y update && apt-get -y install \
git \
libcurl4-gnutls-dev \
iputils-ping \
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

COPY cron_potpod /etc/cron.d/cron_potpod
RUN chmod 0644 /etc/cron.d/cron_potpod
RUN crontab /etc/cron.d/cron_potpod  #Remove?
RUN touch /var/log/cron.log
#CMD cron && tail -f /var/log/cron.log

RUN git clone https://github.com/magnuscj/homedata.git

#RUN rm -f /var/www/html/index.html

RUN mkdir /usr/storage
RUN touch /usr/storage/txt.txt

RUN mkdir /mnt/ramdisk
RUN cd /var/www/html;ln -s /mnt/ramdisk/details.xml details.xml


WORKDIR homedata/container/potpod

#ENTRYPOINT ["/bin/bash","./start.sh"]
