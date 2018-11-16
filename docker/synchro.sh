#!/usr/bin/env bash
OLD_TIME=$(date +%T)
WANAPLAY_TIME=$(curl -Is wanaplay.com | grep Date | awk '{print $6}')
if [ "$OLD_TIME" != "$WANAPLAY_TIME" ]; then
    DATE_SET=$(date +%T -s "$WANAPLAY_TIME")
    echo "server date and wanaplay date differ, setting server date to $DATE_SET"
fi
exit 0
