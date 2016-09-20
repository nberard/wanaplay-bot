FROM php:5.6-cli

RUN apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get -yq install \
        curl \
        git \
        cron \
        zip && \
    rm -rf /var/lib/apt/lists/*

ADD docker/timezone /etc/

RUN dpkg-reconfigure -f noninteractive tzdata

RUN cd /opt && curl -s http://getcomposer.org/installer | php

ADD . /opt/wanaplay-bot
ADD docker/crontab /etc/cron.d/wanaplay-bot
ADD docker/php.ini /usr/local/etc/php/

WORKDIR /opt/wanaplay-bot

CMD ["docker/run.sh"]
