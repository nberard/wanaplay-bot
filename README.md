# Wanaplay Bot

## Introduction

This project is a Symfony application (command only for now) allowing the user to book for a squash court on http://fr.wanaplay.com
Combined with a cron job it can be used to automatically book it every week.

## Installation to run the command

### Manually

```
php composer.phar install
app/console wanaplay:wanaplay_bot:book "<court_time>"
```

with <court_time> a date with format _H:i_ (ex: 17:00)

#### Pre-requisites

* php >= 5.4
* composer

## Installation to automatically book every week

### Manually

Add the following line to your crontab:

```
0 0 * * [day_of_week] /path/to/php /path/to/wanaplay-bot/app/console wanaplay:wanaplay_bot:book "[court_time]" >> /path/to/cron.log 2>&1
```

### With docker

#### From public image

TODO

#### Build and run your image

```
cp .env.dist .env
# adapt it to your needs
docker build -t wanaplay-bot:latest .
docker run -d --env-file=.env wanaplay-bot:latest
```

To watch logs of your previously created container [container_id]

```
docker logs -f [container_id]
```

