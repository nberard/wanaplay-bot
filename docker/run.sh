#!/bin/bash
CRONTAB_PATH=/etc/cron.d/wanaplay-bot
php /opt/composer.phar install -n -q
IFS=', ' read -r -a BOOK_DAYS <<< "$BOOK_DAYS"
for DAY in "${BOOK_DAYS[@]}"; do
    DAY_OF_WEEK=$(date -d$DAY +%u)
    LINE="0 0 * * $DAY_OF_WEEK /usr/local/bin/php /opt/wanaplay-bot/app/console wanaplay:wanaplay_bot:book $BOOK_TIME >> /var/log/cron.log 2>&1"
    echo "$LINE" >> $CRONTAB_PATH
done
echo "" >> $CRONTAB_PATH

echo "Crontab created:"
cat $CRONTAB_PATH

touch /var/log/cron.log
crontab -u root $CRONTAB_PATH
cron -f &
tail -f /var/log/cron.log
